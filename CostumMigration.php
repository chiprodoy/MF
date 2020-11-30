<?php

namespace Illuminate\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CostumMigration
{
    protected $tb;

    public function table($name){
        $this->tb=$name;
        return $this;

    }

    public function requireUp(){
        Schema::table($this->tb, function(Blueprint $table)
        {
                $table->softDeletes();
                $table->timestamps();
                $table->string('user_modify');
        });

    }

}
