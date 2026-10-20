<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    /**
     * Lista los pedidos:
     * - Si el usuario es ROLE_ADMIN, ve todos los pedidos de la tienda.
     * - Si es un cliente normal (ROLE_USER), solo ve sus propios pedidos.
     */
    #[Route('', name: 'api_orders_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] ?User $user,
        OrderRepository $orderRepository,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $orders = $orderRepository->findBy([], ['id' => 'DESC']);
        } else {
            $orders = $orderRepository->findBy(['customer' => $user], ['id' => 'DESC']);
        }

        return $this->json([
            'count' => count($orders),
            'orders' => array_map(static fn (Order $o) => $o->toArray(), $orders),
        ]);
    }

    /**
     * Consulta un pedido específico por ID.
     *
     * SEGURIDAD (OWASP API1 - Broken Object Level Authorization / BOLA / IDOR):
     * Verifica explícitamente que el pedido pertenezca al usuario autenticado
     * o que el usuario tenga rol de administrador (ROLE_ADMIN).
     */
    #[Route('/{id}', name: 'api_orders_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        #[CurrentUser] ?User $user,
        OrderRepository $orderRepository,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $orderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Control de autorización a nivel de objeto (Mitigación de BOLA / IDOR)
        if ($order->getCustomer()?->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'Acceso denegado (Protección BOLA/IDOR): No tienes permiso para consultar el pedido de otro cliente.',
                'code' => Response::HTTP_FORBIDDEN,
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'order' => $order->toArray(),
        ]);
    }

    /**
     * Crea un nuevo pedido para el usuario autenticado.
     *
     * SEGURIDAD DE LÓGICA DE NEGOCIO:
     * 1. El precio unitario y el total se calculan en el servidor desde la BD (ignora precios enviados por el cliente).
     * 2. Se valida que las cantidades sean enteras positivas (> 0) y que exista stock suficiente.
     */
    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] ?User $user,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['error' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $shippingAddress = trim((string) ($data['shippingAddress'] ?? ''));
        $itemsPayload = $data['items'] ?? [];

        if ($shippingAddress === '') {
            return $this->json(['error' => 'La dirección de envío (shippingAddress) es obligatoria.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!is_array($itemsPayload) || count($itemsPayload) === 0) {
            return $this->json(['error' => 'El pedido debe incluir al menos un artículo en "items".'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order = new Order();
        $order->setCustomer($user);
        $order->setShippingAddress($shippingAddress);
        $order->setStatus(Order::STATUS_PENDING);

        foreach ($itemsPayload as $row) {
            $productId = (int) ($row['productId'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                return $this->json([
                    'error' => 'Cada ítem debe tener un productId válido y una cantidad mayor a 0.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $product = $productRepository->find($productId);
            if ($product === null || !$product->isActive()) {
                return $this->json([
                    'error' => sprintf('El producto con ID %d no existe o no está disponible.', $productId),
                ], Response::HTTP_NOT_FOUND);
            }

            if ($product->getStock() < $quantity) {
                return $this->json([
                    'error' => sprintf(
                        'Stock insuficiente para "%s" (disponible: %d, solicitado: %d).',
                        $product->getName(),
                        $product->getStock(),
                        $quantity
                    ),
                ], Response::HTTP_CONFLICT);
            }

            // Descontar stock y usar el precio oficial de la base de datos
            $product->setStock($product->getStock() - $quantity);

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice((float) $product->getPrice());

            $order->addItem($orderItem);
        }

        $order->recalculateTotal();

        $em->persist($order);
        $em->flush();

        return $this->json([
            'message' => 'Pedido creado exitosamente.',
            'order' => $order->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Actualiza el estado de un pedido (Solo ROLE_ADMIN).
     */
    #[Route('/{id}/status', name: 'api_orders_update_status', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(
        int $id,
        Request $request,
        OrderRepository $orderRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $order = $orderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = strtoupper(trim((string) ($data['status'] ?? '')));

        if (!in_array($newStatus, Order::VALID_STATUSES, true)) {
            return $this->json([
                'error' => sprintf('Estado inválido. Estados permitidos: %s', implode(', ', Order::VALID_STATUSES)),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->setStatus($newStatus);
        $em->flush();

        return $this->json([
            'message' => sprintf('Estado del pedido #%d actualizado a %s.', $id, $newStatus),
            'order' => $order->toArray(),
        ]);
    }
}
