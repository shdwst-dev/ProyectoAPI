<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    /**
     * Endpoint público: lista el catálogo de productos con filtros seguros.
     */
    #[Route('', name: 'api_products_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $search = $request->query->get('search');
        $category = $request->query->get('category');

        $products = $productRepository->searchCatalog(
            is_string($search) ? $search : null,
            is_string($category) ? $category : null,
            onlyActive: true
        );

        return $this->json([
            'count' => count($products),
            'products' => array_map(static fn (Product $p) => $p->toArray(), $products),
        ]);
    }

    /**
     * Endpoint público: detalle de un producto por ID.
     */
    #[Route('/{id}', name: 'api_products_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);

        if ($product === null || !$product->isActive()) {
            return $this->json(['error' => 'Producto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['product' => $product->toArray()]);
    }

    /**
     * Endpoint protegido (Solo ROLE_ADMIN): crear nuevo producto.
     * Previene OWASP API5 (Broken Function Level Authorization).
     */
    #[Route('', name: 'api_products_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $product = new Product();
        $product->setName((string) ($data['name'] ?? ''));
        $product->setDescription((string) ($data['description'] ?? ''));
        $product->setPrice((float) ($data['price'] ?? 0));
        $product->setStock((int) ($data['stock'] ?? 0));
        $product->setCategory((string) ($data['category'] ?? 'General'));
        $product->setImageUrl(isset($data['imageUrl']) ? (string) $data['imageUrl'] : null);

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Error de validación en el producto.',
                'details' => $messages,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->persist($product);
        $em->flush();

        return $this->json([
            'message' => 'Producto creado correctamente.',
            'product' => $product->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Endpoint protegido (Solo ROLE_ADMIN): actualizar un producto existente.
     */
    #[Route('/{id}', name: 'api_products_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $product = $productRepository->find($id);

        if ($product === null) {
            return $this->json(['error' => 'Producto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $product->setName((string) $data['name']);
        }
        if (isset($data['description'])) {
            $product->setDescription((string) $data['description']);
        }
        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }
        if (isset($data['stock'])) {
            $product->setStock((int) $data['stock']);
        }
        if (isset($data['category'])) {
            $product->setCategory((string) $data['category']);
        }
        if (array_key_exists('imageUrl', $data)) {
            $product->setImageUrl($data['imageUrl'] !== null ? (string) $data['imageUrl'] : null);
        }
        if (isset($data['active'])) {
            $product->setActive((bool) $data['active']);
        }

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Error de validación.',
                'details' => $messages,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->flush();

        return $this->json([
            'message' => 'Producto actualizado correctamente.',
            'product' => $product->toArray(),
        ]);
    }

    /**
     * Endpoint protegido (Solo ROLE_ADMIN): eliminar o desactivar un producto.
     */
    #[Route('/{id}', name: 'api_products_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = $productRepository->find($id);

        if ($product === null) {
            return $this->json(['error' => 'Producto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Baja lógica (soft-delete) para preservar integridad referencial en pedidos existentes
        $product->setActive(false);
        $em->flush();

        return $this->json([
            'message' => sprintf('El producto #%d ("%s") fue eliminado del catálogo activo.', $id, $product->getName()),
        ]);
    }
}
