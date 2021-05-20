<?php

namespace MF\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

interface FormController
{
    public function fields();
    public function fieldsets();
}

trait ControllerResources
{
    
    public $objModel;
    public $errorMsg;
    
    // HTTP STATUS CODE
    public $successStatus = 200;
    public $errorStatus = 500;
    public $notFoundStatus = 404;//204; //partial content for 206
    public $noContentStatus = 204;//204; //partial content for 206
    public $badRequestStatus =400;
    public $validationErrorStatus =422;

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
    public $theme;
    public $mustCheckingRole=false;
    public $linkedController;

    public function __construct()
    {
        $this->theme=config('app.ui');
        $this->objModel=$this->namaModel::select('*');
        if(!empty($this->namaModel::$relasi)) $this->objModel->with($this->namaModel::$relasi);
        $this->currentUser=Auth::user();

        
        if(empty($this->addRecordURL)){
            $this->addRecordURL=url("api/".$this->controllerName);
        }
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
        $this->mustCheckingRole = (isset($this->needCheckingRole) ? true : false);
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
        if(config('app.ui')){
            $sortMethod=($request->order=='asc' ? false: true);
            $this->page=(empty($request->page) ? $this->page:$request->page);
            if(!isset($request->nopaging)){
                $this->limitRow = (isset($request->rows) ? $request->rows : $this->limitRow);
                $limit=$this->limitRow;
                $this->offset=($this->page*$limit)-$limit;
            }else{
                $this->limitRow=null;
                $limit= $this->limitRow;
            } 
            $this->getModelRecord($this->offset,$limit,$request,$request->sort,$sortMethod);
            return $this->output($request);

        }else{
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
                    'metadata'=>['message'=>$pesan,'code'=>$this->badRequestStatus],
                ]];

                if (0 === strpos($request->headers->get('Accept'), 'application/json')) {
                    return response()->json($respon,$respon['response']['metadata']['code']);
                }else{
        
                    return redirect()->route($defaultRoute)
                    ->withErrors($validator)
                    ->with('responcode',$respon['response']['metadata']['code'])
                    ->with('respon', $respon['response']['metadata']['message'])
                    ->withInput();
                }
                
            }
        }
        if(method_exists($this,'uploadMyFile')){
            $uploaded=$this->uploadMyFile($request);
        } 

        try{
          //  DB::enableQueryLog();
            $m = new $this->namaModel;
            //$m = new \App\Models\User;
            foreach($m->getFillable() as $k => $v){
                if(Str::contains($v, '_file')){
                    if(empty($uploaded[$v])){
                        $m->$v=$m->$v;
                    }else{
                        $m->$v=$uploaded[$v];
                    }
                }else{
                    $m->$v = $request->$v;
                }
            }
            $m->user_modify= ($this->mustCheckingRole ? Auth::user()->name : 'ANONYMOUS');
            $m->user_id=($this->mustCheckingRole ? Auth::id() : 1 );
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
        $formFields=$datas->getFormFields();
        if(config('app.ui')){
            if (View::exists($this->controllerName.'.crud.update')) {
                return view($this->controllerName.'.crud.update',array_merge(get_object_vars($this),compact('datas','formFields')));
            }else{
                
                return view('components.'.$this->theme.'.layout.update',array_merge(get_object_vars($this),compact('datas','formFields')));
            }
        }else{
            if (View::exists($this->controllerName.'.crud.update')) {
            
                return view($this->controllerName.'.crud.update',
                array_merge(get_object_vars($this),compact('datas','formfields')));
            
            }else{
           
                return view('~layouts.component.'.env('COMPONENT_UI').'.crud.update',
                array_merge(get_object_vars($this),compact('datas','formfields')));
            }
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
        if(method_exists($this,'uploadMyFile')){
            $uploaded=$this->uploadMyFile($request);
        } 
        try{

            $m = $this->namaModel::find($id);
            //$m = new \App\Models\User;
            foreach($m->getFillable() as $k => $v){
                if(Str::contains($v, '_file')){
                    if(empty($uploaded[$v])){
                        $m->$v=$m->$v;
                    }else{
                        $m->$v=$uploaded[$v];
                    }
                }else{
                    $m->$v = $request->$v;
                }
            }
            $m->user_modify= ($this->mustCheckingRole ? Auth::user()->name : 'ANONYMOUS');
            $m->user_id=($this->mustCheckingRole ? Auth::id() : 1 );

            $m->save();

           // $rec=$this->namaModel::find($id)->update($request->all());
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

            return redirect()->route($this->controllerName.'.index')
            ->with('responcode',$respon['response']['metadata']['code'])
            ->with('respon', $respon['response']['metadata']['message']);
        }
    }

    public function afterDestroy(){
        
    }

    public function getModelRecord($offset,$limit,$keyword=null,$orderby='id',$desc=true){
        
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

    public function JSONTemplate($data,$responCode,$total=null){
        if(config('app.ui')){
            return [
                
                    'total'=>(empty($total) ? $this->totalRec : $total),
                    'rows'=>$data,
                    'page'=>$this->page,
                    'limit'=>$this->limitRow,
                    'total_page'=>((empty($this->limitRow)) ? 1 : ceil($this->totalRec/$this->limitRow)),
                    'metadata'=>['message'=>'ok','code'=>$responCode],
               
            ];
        }else{
            return [
                'response'=>[
                    'total_record'=>$this->totalRec,
                    'list'=>$data,
                    'page'=>$this->page,
                    'limit'=>$this->limitRow,
                    'total_page'=>((empty($this->limitRow)) ? 1 : ceil($this->totalRec/$this->limitRow)),
                    'metadata'=>['message'=>'ok','code'=>$responCode],
                ]
            ];
        }

    }
    public function outputJSON(){
        //TODO : auth
     //   Gate::authorize('read', $this->namaModel);
     //   Gate::authorize('read-own', $this->namaModel);
     
        if($this->totalRec < 1 && empty($this->errorMsg)){
            return response()
            ->json($this->JSONTemplate($this->objModel->get(),
            $this->noContentStatus),$this->noContentStatus);
        }elseif($this->totalRec > 0 && empty($this->errorMsg)){
            return response()->json($this->JSONTemplate($this->objModel->get(),
            $this->successStatus),$this->successStatus);
        }else{
            return response()->json([
                'response' => [
                    'metadata'=>[
                        'message' => "Error:." . $this->errorMsg,
                        'code'=>$this->errorStatus],
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
         $viewAble=$this->namaModel::viewable();
         /*
        *    for backward and new version compatibility
        *
        */
        if(config('app.ui')){
            if (View::exists($this->controllerName.'.crud.index')) {
                return view($this->controllerName.'.crud.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
                'totalPage','prev','next','filterFields','formfields','viewAble')));
            }else{
                return view('components.'.config('app.ui').'.layout.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
                'totalPage','prev','next','filterFields','formfields','viewAble')));
            }
        }else{
            if (View::exists($this->controllerName.'.crud.index')) {
                return view($this->controllerName.'.crud.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
                'totalPage','prev','next','filterFields','formfields')));
            
            }else{
                if(class_exists(\App\View\Components\Tailwindcss\Crud\Index::class)) {
                    $viewObject=new \App\View\Components\Tailwindcss\Crud\Index($this->controllerName,$this->namaModel,$this->menu,$this->col);
                    return $viewObject->render();
                } else{
                    
                    return view('~layouts.component.'.env('COMPONENT_UI').'.crud.index',array_merge(get_object_vars($this),compact('datas','keyword','page',
                    'totalPage','prev','next','filterFields','formfields')));
                }
            }
    
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
            if(config('app.ui')){
                return view('components.'.config('app.ui').'.layout.create',array_merge(get_object_vars($this),compact('datas','formfields')));
            }else{
                return view('~layouts.component.'.env('COMPONENT_UI').'.crud.create',array_merge(get_object_vars($this),compact('datas','formfields')));
            }
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
        $viewAble=$this->namaModel::viewable();
        if(config('app.ui')){
            if (View::exists($this->controllerName.'.crud.show')) {
                return view($this->controllerName.'.crud.show',
                array_merge(get_object_vars($this),compact('datas',
                'formfields','viewAble')));
            }else{
                return view('components.'.config('app.ui').'.layout.show',
                array_merge(get_object_vars($this),compact('datas',
                'formfields','viewAble')));
            }
        }else{
            if (View::exists($this->controllerName.'.crud.show')) {
                
                return view($this->controllerName.'.crud.show',
                array_merge(get_object_vars($this),compact('datas','formfields')));
            
            }else{
            
                return view('~layouts.component.'.env('COMPONENT_UI').'.crud.show',
                array_merge(get_object_vars($this),compact('datas','formfields')));
            }
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
    public function export() 
    {
        if(isset($this->exportModel))
        return Excel::download(new $this->exportModel, $this->exportFileName.'.xlsx');
    }
}
/*
todo : fix upload file
*/
trait UploadFile{
    public function uploadMyFile(){
        $path = Storage::disk('public')->put('photos', new File('/path/to/photo'));
    }
}

trait RoleAbility{

    private $needCheckingRole=true;
}
