<?php

namespace MF\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

interface FormController
{
    public function fields();
    public function fieldsets();
}

trait ControllerResources
{
    
    public $objModel;
    public $successStatus = 200;
    public $errorMsg;
    public $errorStatus = 500;
    public $notFoundStatus = 404;

    public $col;
    public $limitRow = 100;
    public $totalRec;
    public $page=1;
    public $offset=0;
    public $updateAction;
    public $destroyAction;
    public $saveAction;
    public $readAction;
    public $currentUser;



    public function __construct()
    {
        $this->objModel=$this->namaModel::select('*');
        if(!empty($this->namaModel::$relasi)) $this->objModel->with($this->namaModel::$relasi);
        $this->currentUser=Auth::user();

        if(empty($this->updateAction)){
            $this->updateAction=url("api/".$this->controllerName);
        }
        if(empty($this->destroyAction)){
            $this->destroyAction=url("api/".$this->controllerName);
        }
        if(empty($this->saveAction)){
            $this->saveAction=url("api/".$this->controllerName);
        }
        if(empty($this->readAction)){
            $this->readAction=url("api/".$this->controllerName);
        }
       // $this->controllerName = Route::currentRouteName();
    }

    public function setObjModel($cls){
        $this->namaModel=$cls;
    }
        /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
      
            $this->page=(empty($request->p) ? $this->page:$request->p);
            if(!isset($request->nopaging)){
                $limit=$this->limitRow;
                $this->offset=($this->page*$limit)-$limit;
            }else{
                $this->limitRow=null;
                $limit= $this->limitRow;
            } 
            $this->getModelRecord($this->offset,$limit,$request);
            return $this->output($request);
       
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $defaultRoute=$this->controllerName.'.index';
        if(method_exists($this,'validasiInput')) {
            $validator=$this->validasiInput($request);
          //  $validator->validate();
            if ($validator->fails()) {
                $defaultRoute = $this->controllerName.'.create';
                $pesan='';
                $messages = $validator->errors();
                foreach ($messages->all() as $message) {
                     $pesan=$pesan." ".$message;
                }
                $respon= ['response'=>[
                    'metadata'=>['message'=>$pesan,'code'=>$this->errorStatus],
                ]];

                if (0 === strpos($request->headers->get('Accept'), 'application/json')) {
                    return response()->json($respon,$respon['response']['metadata']['code']);
                }else{
        
                    return redirect()->route($defaultRoute)
                    ->withErrors($validator)
                    ->withInput()
                    ->with('responcode',$respon['response']['metadata']['code'])
                    ->with('respon', $respon['response']['metadata']['message']);
                }
                
            }
        }
        
        try{
          //  DB::enableQueryLog();
            $m = new $this->namaModel;
            //$m = new \App\Models\User;
            foreach($m->getFillable() as $k => $v){
                $m->$v = $request->$v;
            }
            $m->user_modify=Auth::user()->name;
            $m->user_id=Auth::id();

            $m->save();
           // dd(DB::getQueryLog());
            $respon=['response'=>[
                'metadata'=>['message'=>'Data Berhasil disimpan','code'=>$this->successStatus],
            ]];

        }catch (\Exception $exception) {
            logger()->error($exception);
            $this->errorMsg =  $exception;

            $defaultRoute=$this->controllerName.'.create';
            
            $respon= ['response'=>[
                'metadata'=>['message'=>'Data gagal disimpan'.substr($exception,0,1000).'...','code'=>$this->errorStatus],
            ]];
        }

        if (0 === strpos($request->headers->get('Accept'), 'application/json') || $request->ajax()) {
            return response()->json($respon,$respon['response']['metadata']['code']);
        }else{
            return redirect()->route($defaultRoute)
            ->with('responcode',$respon['response']['metadata']['code'])
            ->with('respon', $respon['response']['metadata']['message']);
        }

        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $datas=$this->namaModel::find($id);
        $formfields=$datas->getFormFields();
        
         if (View::exists($this->controllerName.'.crud.create')) {
            
            return view($this->controllerName.'.crud.edit',
            array_merge(get_object_vars($this),compact('datas','formfields')));
        
        }else{
           
            return view('~layouts.component.'.env('COMPONENT_UI').'.crud.edit',
            array_merge(get_object_vars($this),compact('datas','formfields')));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id,Request $request)
    {
        $defaultRoute = $this->controllerName.'.index';
        
        if(method_exists($this,'validasiInput')) {
            $validator=$this->validasiInput($request);
          //  $validator->validate();
            if ($validator->fails()) {
                $defaultRoute = $this->controllerName.'.edit';
                $pesan='';
                $messages = $validator->errors();
                foreach ($messages->all() as $message) {
                     $pesan=$pesan." ".$message;
                }
                $respon= ['response'=>[
                    'metadata'=>['message'=>$pesan,'code'=>$this->errorStatus],
                ]];

                if (0 === strpos($request->headers->get('Accept'), 'application/json')) {
                    return response()->json($respon,$respon['response']['metadata']['code']);
                }else{
        
                    return redirect()->route($defaultRoute,$id)
                    ->withErrors($validator)
                    ->withInput()
                    ->with('responcode',$respon['response']['metadata']['code'])
                    ->with('respon', $respon['response']['metadata']['message']);
                }
                
            }
        }
        
        try{
            $rec=$this->namaModel::find($id)->update($request->all());
            //$rec->kode_booking='123456789';
            $respon=['response'=>[
                'metadata'=>['message'=>'Data Berhasil diupdate','code'=>$this->successStatus],
            ]];

        }catch (\Exception $exception) {
            logger()->error($exception);
            $defaultRoute = $this->controllerName.'.edit';
           $respon= ['response'=>[
                'metadata'=>['message'=>'Data gagal diupdate'.substr($exception,0,100).'...','code'=>$this->errorStatus],
            ]];
            
        }

        if (0 === strpos($request->headers->get('Accept'), 'application/json')) {
            return response()->json($respon,$respon['response']['metadata']['code']);
        }else{

            return redirect()->route($defaultRoute,$id)
            ->withErrors($validator)
            ->with('responcode',$respon['response']['metadata']['code'])
            ->with('respon', $respon['response']['metadata']['message']);
        }

    }

    private function checkDelPermission(){
        if (Gate::none(['delete', 'delete-own'], $this->namaModel)) {
            $respon= ['response'=>[
                'metadata'=>['message'=>'anda tidak memiliki hak akses','code'=>403],
            ]];

        } 
    }
    private function validasiHapus(){
        return true;
    }

    public function beforeDestroy(Array $param){
        $this->checkDelPermission();
        $this->validasiHapus($param);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id,Request $request)
    {

        $this->beforeDestroy(['id'=>$id,$request]);
        
        try{
            $delObj=$this->namaModel::where('id',$id);
            if (Gate::check('delete-own', [$this->namaModel])) {
                $delObj->where('user_id','=',Auth::user()->id);
            }
            $delObj->delete();
            
        $this->afterDestroy();

        $respon=['response'=>[
            'metadata'=>['message'=>'Data Berhasil dihapus','code'=>$this->successStatus],
        ]];

        }catch (\Exception $exception) {
            logger()->error($exception);

           $respon= ['response'=>[
                'metadata'=>['message'=>'Data gagal dihapus'.substr($exception,0,100).'...','code'=>$this->errorStatus],
            ]];
        }

        if (0 === strpos($request->headers->get('Accept'), 'application/json')) {
            return response()->json($respon,$respon['response']['metadata']['code']);
        }else{

            return redirect()->route($this->controllerName)
            ->with('responcode',$respon['response']['metadata']['code'])
            ->with('respon', $respon['response']['metadata']['message']);
        }
    }

    public function afterDestroy(){
        
    }

    public function getModelRecord($offset,$limit,$keyword=null,$orderby='',$desc=true){
        
        try{

            if (Gate::check('read-own', [$this->namaModel])) {
                
                $this->objModel->where('user_id','=',Auth::user()->id);
            }

            if(empty($keyword->keyword)){
                foreach($keyword->request as $k => $v){
                    if(in_array(strtolower($k), array_map('strtolower',$this->namaModel::searchable()))){
                        $this->objModel->where($k,'=',"$v");
                    }
                }
            }elseif(!empty($keyword->keyword)){
                foreach($this->namaModel::searchable() as $k){
                    $this->objModel->orWhere($k,'like','%'.$keyword->keyword.'%');
                }
            }

            $recPaging=$this->objModel;
            $this->totalRec= $recPaging->count();

            if(!empty($limit)){
                $this->objModel->limit($limit);
            }
            
            if($offset>0){
               $this->objModel->offset($offset);
            }
            if(!empty($orderby)){
                if($desc) $this->objModel->orderBy($orderby, 'desc');
                else  $this->objModel->orderBy($orderby, 'asc');
            }
            
        } catch (\Exception $exception) {
            logger()->error($exception);
            $this->errorMsg =  $exception;
        }

    }

    public function outputJSON(){
        //TODO : auth
     //   Gate::authorize('read', $this->namaModel);
     //   Gate::authorize('read-own', $this->namaModel);
        if($this->totalRec < 1){
            return response()->json([
                'response'=>[
                    'total_record'=>$this->totalRec,
                    'list'=>$this->objModel->get(),
                    'page'=>$this->page,
                    'limit'=>$this->limitRow,
                    'total_page'=>((empty($this->limitRow)) ? 1 : ceil($this->totalRec/$this->limitRow)),
                    'metadata'=>['message'=>'ok','code'=>$this->notFoundStatus],
                ]
            ],$this->notFoundStatus);
        }elseif($this->totalRec > 0 && empty($this->errorMsg)){
            return response()->json([
                'response'=>[
                    'total_record'=>$this->totalRec,
                    'list'=>$this->objModel->get(),
                    'page'=>$this->page,
                    'limit'=>$this->limitRow,
                    'total_page'=>((empty($this->limitRow)) ? 1 : ceil($this->totalRec/$this->limitRow)),
                    'metadata'=>['message'=>'ok','code'=>$this->successStatus],
                ]
                ],$this->successStatus);
        }else{
            return response()->json([
                'response' => [
                    'metadata'=>[
                        'message' => "Error:." . $this->errorMsg,'code'=>$this->errorStatus],
                    ]],$this->errorStatus
            );
        }
    }

    public function outputWeb(Request $request)
    {
       // $token = auth()->user()->createToken('Personal Access Token')->accessToken;
        $title = $this->getTitle();
        $className = $this->getClassName();
        $this->col=$this->namaModel::viewable();
        $keyword=$request->keyword;
        $page=$this->page;
        $totalPage=$this->totalRec/$this->limitRow;
        $prev=$page-1;
        $next=$page+1;
        $datas=$this->objModel->get();
        $filterFields=$this->namaModel::getFilterable();
        $obj = new $this->namaModel();
         $formfields=$obj->getFormFields();
       // $fields = $this->fields();
       // $fieldsets = $this->fieldsets();
       // $inlines_name = $this->getInlinesName();
        if (View::exists($this->controllerName.'.crud.index')) {
            
            return view($this->controllerName.'.crud.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
            'totalPage','prev','next','filterFields','formfields')));
        
        }else{
           
            return view('~layouts.component.'.env('COMPONENT_UI').'.crud.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
            'totalPage','prev','next','filterFields','formfields')));
        }

    }
    public function output(Request $request){
        if (0 === strpos($request->headers->get('Accept'), 'application/json') || $request->ajax()) {
            return $this->outputJSON();

        }else{
            return $this->outputWeb($request);
        }
    }

    public function getTitle()
    {
        $re = '/(?#! splitCamelCase Rev:20140412)
            # Split camelCase "words". Two global alternatives. Either g1of2:
              (?<=[a-z])      # Position is after a lowercase,
              (?=[A-Z])       # and before an uppercase letter.
            | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
              (?=[A-Z][a-z])  # and before upper-then-lower case.
            /x';
        $a = preg_split($re, $this->title != null ? $this->title : $this->getClassName());

        return implode(' ', $a);
    }

   /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
         $datas = new $this->namaModel();
         $formfields=$datas->getFormFields();
         if (View::exists($this->controllerName.'.crud.create')) {
            
            return view($this->controllerName.'.crud.create',array_merge(get_object_vars($this),compact('datas','formfields')));
        
        }else{
           
            return view('~layouts.component.'.env('COMPONENT_UI').'.crud.create',array_merge(get_object_vars($this),compact('datas','formfields')));
        }
        // return view('admin.edit',array_merge(get_object_vars($this),compact('datas','formfields')));
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $datas=$this->namaModel::find($id);
        $formfields=$datas->getFormFields();
        
         if (View::exists($this->controllerName.'.crud.create')) {
            
            return view($this->controllerName.'.crud.show',
            array_merge(get_object_vars($this),compact('datas','formfields')));
        
        }else{
           
            return view('~layouts.component.'.env('COMPONENT_UI').'.crud.show',
            array_merge(get_object_vars($this),compact('datas','formfields')));
        }
    }


        /**
     * @return string
     * @throws \ReflectionException
     */
    protected function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
