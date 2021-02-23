<?php

namespace Laravel\MF;

use App\Models\Menu;

trait HasMenuFacades
{
    public $menu;
    public function __construct(){
        $this->menu=self::getMenu();
        
    }

    public static function getMenu(){
        
       // $parentMenu=Menu::where('parent_id','0')->with('childrenMenu')->get();
       $parentMenu=Menu::where('parent_id','0')->orWhere('parent_id',Null)
       ->orderBy('sort_order','asc')->with('childrenMenu')->get();
       return $parentMenu;
        
    }
}
