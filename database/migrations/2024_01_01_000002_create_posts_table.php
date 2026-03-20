<?php
namespace Pendasi\Rest\Database;

class CreatePostsTable extends Migration {
    
    public function up() {
        $this->createTable('posts', function(SchemaBuilder $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title', 255);
            $table->text('content');
            $table->boolean('published', false);
            $table->timestamps();
            $table->foreignKey('user_id', 'users');
        });
    }

    public function down() {
        $this->dropTableIfExists('posts');
    }
}
