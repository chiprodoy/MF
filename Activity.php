<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['user_id','content_id','user_name','model_name','model_data',
    'action','description','ip_address'];

    //
}
