<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Conexión directa a MongoDB
        $client = new Client(
            'mongodb://'.env('DB_HOST', '127.0.0.1').':'.env('DB_PORT', '27017')
        );
        
        $collection = $client->selectDatabase(env('DB_DATABASE', 'panaderia'))->users;

        // Limpiar colección
        $collection->deleteMany([]);

        // Datos de usuarios a insertar
        $users = [
            [
                'name' => 'Administrador',
                'email' => 'admin@panaderia.com',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
                'created_at' => new UTCDateTime(now()->timestamp * 1000),
                'updated_at' => new UTCDateTime(now()->timestamp * 1000)
            ],
            [
                'name' => 'Vendedor 1',
                'email' => 'vendedor1@panaderia.com',
                'password' => Hash::make('Vendedor1'),
                'role' => 'vendedor',
                'created_at' => new UTCDateTime(now()->timestamp * 1000),
                'updated_at' => new UTCDateTime(now()->timestamp * 1000)
            ],
            [
                'name' => 'Cliente Ejemplo',
                'email' => 'cliente@panaderia.com',
                'password' => Hash::make('Cliente123'),
                'role' => 'cliente',
                'created_at' => new UTCDateTime(now()->timestamp * 1000),
                'updated_at' => new UTCDateTime(now()->timestamp * 1000)
            ]
        ];

        // Insertar usuarios
        $collection->insertMany($users);

        $this->command->info('Usuarios insertados correctamente en MongoDB!');
    }
}