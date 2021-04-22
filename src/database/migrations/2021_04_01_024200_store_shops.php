<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class StoreShops extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('store_shops', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type_shop');
            $table->string('store_name');
            $table->string('store_front');
            $table->string('api_key');
            $table->string('secret_key');
            $table->bigInteger('customer_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('store_shops');
    }
}
