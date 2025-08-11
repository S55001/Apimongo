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
        $client = new Client('mongodb://' . env('DB_HOST','127.0.0.1') . ':' . env('DB_PORT','27017'));
        $dbName = env('DB_DATABASE','panaderia');
        $collection = $client->selectDatabase($dbName)->users;

        $now = new UTCDateTime(now()->getTimestampMs());

        $collection->deleteMany([]);

        $collection->insertMany([
            [
                'name' => 'Administrador',
                'email' => 'admin@panaderia.com',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Vendedor 1',
                'email' => 'vendedor1@panaderia.com',
                'password' => Hash::make('Vendedor1'),
                'role' => 'vendedor',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Cliente Ejemplo',
                'email' => 'cliente@panaderia.com',
                'password' => Hash::make('Cliente123'),
                'role' => 'cliente',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }
}
