<?php

namespace App\DataFixtures;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Usuarios de prueba (1 Administrador y 2 Clientes para pruebas de roles y BOLA/IDOR)
        $admin = new User();
        $admin->setName('Administrador Tienda');
        $admin->setEmail('admin@tienda.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $ana = new User();
        $ana->setName('Ana García (Cliente 1)');
        $ana->setEmail('ana@cliente.com');
        $ana->setRoles(['ROLE_USER']);
        $ana->setPassword($this->passwordHasher->hashPassword($ana, 'cliente123'));
        $manager->persist($ana);

        $carlos = new User();
        $carlos->setName('Carlos López (Cliente 2)');
        $carlos->setEmail('carlos@cliente.com');
        $carlos->setRoles(['ROLE_USER']);
        $carlos->setPassword($this->passwordHasher->hashPassword($carlos, 'cliente123'));
        $manager->persist($carlos);

        // 2. Catálogo inicial de productos
        $productsData = [
            [
                'name' => 'Laptop Pro 14" M3',
                'description' => 'Laptop de alto rendimiento con 16GB RAM, SSD NVMe 512GB y pantalla Retina.',
                'price' => 24999.00,
                'stock' => 12,
                'category' => 'Computadoras',
            ],
            [
                'name' => 'Teclado Mecánico RGB Keychron',
                'description' => 'Teclado mecánico inalámbrico 75% con switches táctiles y chasis de aluminio.',
                'price' => 2150.00,
                'stock' => 25,
                'category' => 'Periféricos',
            ],
            [
                'name' => 'Llave de Seguridad FIDO2 YubiKey 5C NFC',
                'description' => 'Token físico de autenticación multifactor (2FA/MFA) USB-C y NFC.',
                'price' => 1450.00,
                'stock' => 40,
                'category' => 'Seguridad',
            ],
            [
                'name' => 'Monitor UltraWide 34" IPS 144Hz',
                'description' => 'Monitor curvo WQHD ideal para programación, auditoría y multitarea.',
                'price' => 8990.00,
                'stock' => 8,
                'category' => 'Monitores',
            ],
            [
                'name' => 'Audífonos Sony WH-1000XM5',
                'description' => 'Audífonos inalámbricos con cancelación activa de ruido y 30 horas de batería.',
                'price' => 6499.00,
                'stock' => 15,
                'category' => 'Audio',
            ],
            [
                'name' => 'Router Wi-Fi 6 VPN Firewall',
                'description' => 'Router de seguridad con soporte nativo para WireGuard, OpenVPN y VLANs.',
                'price' => 3290.00,
                'stock' => 18,
                'category' => 'Seguridad',
            ],
        ];

        $products = [];
        foreach ($productsData as $item) {
            $product = new Product();
            $product->setName($item['name']);
            $product->setDescription($item['description']);
            $product->setPrice($item['price']);
            $product->setStock($item['stock']);
            $product->setCategory($item['category']);
            $manager->persist($product);
            $products[] = $product;
        }

        // 3. Pedido #1 (Pertenece a Ana - Cliente 1)
        $orderAna = new Order();
        $orderAna->setCustomer($ana);
        $orderAna->setShippingAddress('Av. Universidad 1024, Col. Centro');
        $orderAna->setStatus(Order::STATUS_PAID);

        $item1 = new OrderItem();
        $item1->setProduct($products[1]); // Teclado Mecánico
        $item1->setQuantity(1);
        $item1->setUnitPrice((float) $products[1]->getPrice());
        $orderAna->addItem($item1);

        $item2 = new OrderItem();
        $item2->setProduct($products[2]); // YubiKey
        $item2->setQuantity(2);
        $item2->setUnitPrice((float) $products[2]->getPrice());
        $orderAna->addItem($item2);

        $orderAna->recalculateTotal();
        $manager->persist($orderAna);

        // 4. Pedido #2 (Pertenece a Carlos - Cliente 2, ideal para probar protección IDOR/BOLA desde la cuenta de Ana)
        $orderCarlos = new Order();
        $orderCarlos->setCustomer($carlos);
        $orderCarlos->setShippingAddress('Calle Reforma 450, Int. 8B');
        $orderCarlos->setStatus(Order::STATUS_SHIPPED);

        $item3 = new OrderItem();
        $item3->setProduct($products[0]); // Laptop Pro
        $item3->setQuantity(1);
        $item3->setUnitPrice((float) $products[0]->getPrice());
        $orderCarlos->addItem($item3);

        $orderCarlos->recalculateTotal();
        $manager->persist($orderCarlos);

        $manager->flush();
    }
}
