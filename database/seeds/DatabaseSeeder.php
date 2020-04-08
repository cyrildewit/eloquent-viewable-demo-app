<?php

use App\Post;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Post::create([
            'title' => 'Somthing innnnnnn',
            'body' => 'ssssssssssssssssssssssssss',
        ]);

        // $this->call(UsersTableSeeder::class);
    }
}
