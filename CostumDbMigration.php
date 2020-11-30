<?php

namespace Laravel\MF;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CostumDbMigration
{

    public function table($name){
        $this->tb=$name;
        return $this;

    }

    public function powerup(){
      /*  if (!Schema::hasTable($this->tb)) {
            $this->up();
            Schema::table($this->tb, function(Blueprint $table)
            {
                    $table->softDeletes();
                    $table->timestamps();
                    $table->string('user_modify');
            });
        }
*/
        Schema::table($this->tb, function(Blueprint $table)
        {
                $table->softDeletes();
                $table->timestamps();
                $table->string('user_modify');
                $table->integer('user_id');
        });

    }

}
