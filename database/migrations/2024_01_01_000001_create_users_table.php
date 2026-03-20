<?php
namespace Pendasi\Rest\Database;

class CreateUsersTable extends Migration {
    
    public function up() {
        $this->createTable('users', function(SchemaBuilder $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->unique('email');
            $table->string('password', 255);
            $table->timestamps();
        });
    }

    public function down() {
        $this->dropTableIfExists('users');
    }
}
