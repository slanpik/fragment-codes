<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Role;
use App\Models\User;
use App\Models\Register\Document;
use App\Models\Register\Country;
use App\Models\Partner\Partner;
use App\Models\Partner\PartnerUser;
Use App\Http\Requests\Shared\UserRequest;
Use App\Http\Requests\Admin\Partner\PartnerRequest;
Use App\Http\Requests\Admin\Partner\PartnerUserRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class PartnerController extends Controller
{

  function __construct()
  {
       $this->middleware(['role:admin']);
       $this->middleware(['permission:partner-edit'])->only(['editUser', 'editPartner']);
       $this->middleware(['permission:partner-create'])->only(['createPartner', 'createPartner_User', 'createUserForPartner', 'createAdminPartner']);
       $this->middleware(['permission:partner-delete'])->only(['deletePartner']);
       $this->middleware(['permission:partner-approved'])->only(['updateStatusPartner']);
  }


    /**
    *para index de partner
    * @param recibe un request que sera la peticion de busqueda
    * @return view Lista los partner y usuarios
    * @return partnes se envia los objetos de los partners
    */
    public function indexPartner(Request $request){


        $partners = Partner::name($request->name)->orderBy('id', 'DESC')->paginate(15);


        return view('admin.partners.indexPartner', compact('partners'));
    }

    /**
    * Se da paso a la interfaz de creacion del partner
    * @return countrys para la creacion del partner
    * @return documents para la creacion del partner
    */
    public function createPartner(){
        $countrys = Country::get();
        $documents = Document::get();
        return view('admin.partners.createPartner', compact('countrys', 'documents'));
    }

    /**
    * Se da paso a la interfaz de creacion del partner en un usuario
    * @param $idUser se recibe el id del usuario que se le agrega el nuevo partner
    * @return countrys para la creacion del partner
    * @return documents para la creacion del partner
    * @return name el nombre y apellido del usuario al cual se le creara el partner.
    * @return $idUser para la creacion del partner
    */
    public function createPartner_User($idUser){
        $countrys = Country::get();
        $documents = Document::get();
        $user = User::find($idUser);
        $name= $user->name.' '.$user->lastName;

        return view('admin.partners.createPartnerOnUser', compact('countrys', 'documents', 'idUser', 'name'));
    }

    /**
    * Se da paso a la interfaz de creacion del usuario bajo ese partner
    * @param $idPartner id del partner que desea crear un Usuario
    * @return $countrys para la creacion del usuario
    * @return $documents para la creacion del usuario
    * @return $idPartner para la creacion del usuario
    */
    public function createUserForPartner($idPartner){
        $countrys = Country::get();
        $documents = Document::get();
        $partner= Partner::find($idPartner);
        $name = ucfirst($partner->name);
        return view('admin.partners.createPartnerUser', compact('countrys', 'documents', 'idPartner', 'name'));
    }

    /**
    * Se da paso a la interfaz de creacion del usuario como administrador ese partner
    * @param $idPartner id del partner que desea crear un Usuario
    * @return $countrys para la creacion del usuario
    * @return $documents para la creacion del usuario
    * @return $idPartner para la creacion del usuario
    */
    public function createAdminPartner($idPartner){
        $countrys = Country::get();
        $documents = Document::get();
        $partner= Partner::find($idPartner);
        $name = ucfirst($partner->name);
        return view('admin.partners.createAdminPartner', compact('countrys', 'documents', 'idPartner', 'name'));
    }

    /**
    * Es para crear un Partner y su Usuario
    * @param $request ['remember_token','name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender' ] son los datos ingresados para guardar el usuario
    * @param $request ['namePartner', 'document_partnerid', 'documentPartner', 'address', 'emailPartner', 'phonePartner', 'country_id', 'user_id' ] son los datos ingresados para guardar el partner
    * @return redirect a la view indexPartner
    */
    public function storePartner(PartnerUserRequest $request ){

        //Se capturan los campos y se crea un objeto User para guardarlo en la tabla Users como partner
        $UserForPartner= new User();
        $UserForPartner->remember_token = $request->_token;
        $UserForPartner->name = $request->name;
        $UserForPartner->lastName = $request->lastName;
        $UserForPartner->document_id = $request->document_id;
        $UserForPartner->document = strval($request->document);
        $UserForPartner->email = $request->email;
        $UserForPartner->password = bcrypt($request->password);
        $UserForPartner->country_id = $request->country_id;
        $UserForPartner->phone = strval($request->phone);
        $UserForPartner->birthDate = $request->birthDay;
        $UserForPartner->gender = $request->gender;

        //Se guarda el objeto en la BD
        $UserForPartner->save();

        //Se guarda el role de este usuario como partner
        $UserForPartner->attachRole(Role::where('name','partner')->first());

        //Se capturan los datos y se guardan en un objeto Partner para guardarlo en la tabla Partners

        $partner = new Partner();
        $partner->name = strtoupper($request->namePartner);
        $partner->document_id = $request->document_partnerid;
        $partner->document = strval($request->documentPartner);
        $partner->address = strtoupper($request->address);
        $partner->email= $request->emailPartner;
        $partner->phone = strval($request->phonePartner);
        $partner->country_id = $request->country_id;

        //Se guarda el objeto en la BD
        $partner->save();

        //Se crea un objeto para guardar la relacion entre usuarios y partner
        $partnerOnUser= new PartnerUser();
        $partnerOnUser->user_id = $UserForPartner->id;
        $partnerOnUser->partner_id = $partner->id;
        //Se guarda el objeto en la BD
        $partnerOnUser->save();

        return redirect()->route('partner.index');

    }

    /**
    * Es para crear un nuevo Usuario con ese partner
    * @param $request ['remember_token','name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender' ] son los datos ingresados para guardar el usuario
    * @return redirect a la view historyPartner
    */
    public function storeUser(UserRequest $request){

        $User= new User();
        $User->remember_token = $request->_token;
        $User->name = $request->name;
        $User->lastName = $request->lastName;
        $User->document_id = $request->document_id;
        $User->document = strval($request->document);
        $User->email = $request->email;
        $User->password = bcrypt($request->password);
        $User->country_id = $request->country_id;
        $User->phone = strval($request->phone);
        $User->birthDate = $request->birthDay;
        $User->gender = $request->gender;

        //Guardando el nuevo objeto en la DB
        $User->save();

        //Agregar el nuevo usuario el role de user
        $User->attachRole(Role::where('name','user')->first());



        //Creando la relacion del partner y un estudiante en la tabla pivote

        $relacion = new PartnerUser();
        $relacion->user_id = $User->id;
        $relacion->partner_id = $request->idPartner;

        //Guardando el objeto en la DB

        $relacion->save();

        $idPartner = $request->idPartner;


        return redirect()->route('partner.history', $idPartner);

    }

    /**
    * Es para crear un Partner a un Usuario ya existente
    * @param $request ['namePartner', 'document_partnerid', 'documentPartner', 'address', 'emailPartner', 'phonePartner', 'country_id', 'user_id' ] son los datos ingresados para guardar el partner
    * @param $idUser id del usuario para guardar el partner
    * @return redirect a la view indexPartner
    */
    public function storePartner_User(PartnerRequest $request, $idUser ){

        //Se capturan los datos y se guardan en un objeto Partner para guardarlo en la tabla Partners

        $partner = new Partner();
        $partner->name = strtoupper($request->name);
        $partner->document_id = $request->document_partnerid;
        $partner->document = strval($request->document);
        $partner->address = strtoupper($request->address);
        $partner->email= $request->email;
        $partner->phone = strval($request->phone);
        $partner->country_id = $request->country_id;

        //Se guarda el objeto en la BD
        $partner->save();

        //Se crea un objeto para guardar la relacion entre usuarios y partner
        $partnerOnUser= new PartnerUser();
        $partnerOnUser->user_id = $idUser;
        $partnerOnUser->partner_id = $partner->id;
        //Se guarda el objeto en la BD
        $partnerOnUser->save();

        return redirect()->route('partner.index');

    }

    /**
    * Se hace el llamado a la interfaz de editar el usuario el cual esta asociado al partner
    *
    * @param $id id del usuario que se desea editar
    * @return view vista de editar usuarios
    * @return $documents se envia el objeto con los datos de la base de datos
    * @return $countrys se envia el objeto con los datos de la base de datos
    * @return $user se envia el objeto con los datos de la base de datos dependiendo si es un usuario
    */
    public function editUser($id){
        $documents = Document::get();
        $countrys = Country::get();
        $user = User::find($id);

        return view('admin.partners.editUser', compact('user', 'countrys', 'documents'));

    }

    /**
    * Se hace el llamado a la interfaz de editar el partner seleccionado
    *
    * @param $id id del partner que se desea editar
    * @return view vista de editar partner
    * @return $documents se envia el objeto con los datos de la base de datos
    * @return $countrys se envia el objeto con los datos de la base de datos
    * @return $partner se envia el objeto con los datos de la base de datos dependiendo si es un partner
    */
    public function editPartner($id){
        $documents = Document::get();
        $countrys = Country::get();
        $partner = Partner::find($id);

        return view('admin.partners.editPartner', compact('partner', 'countrys', 'documents'));

    }


    /**
     * Se listan todos los usuarios que pertenecen a ese Partner
     *
     * @param $idPartner id para listar los usuarios de ese partner
     * @return view lista de los usuarios de un partner
     * @return $partner el objeto del cual se mostraran los usuarios
     */
    public function historyPartner($idPartner){

        $partner= Partner::find($idPartner);

         return view('admin.partners.historyPartner', compact('partner'));

    }

    /**
     * Se recibe el comando para borrar un partner especifico
     *
     * @param $id id del elemento que se va a borrar de la BD
     * @return view para el index de partner
     */
    public function deletePartner($id){

        $partner = Partner::find($id);
        $partner->delete();

        return redirect()->route('partner.index');
    }

    /**
     * Se recibe el comando para hacer el detached de un partner a usuario
     *
     * @param $id id del usuario que se va a detached de la BD
     * @param $partner_id id del partner que se va a detached de la BD
     * @return view para el index de partner
     */
     public function detached($id, $partner_id){

        $partner = PartnerUser::where('user_id', $id)->where('partner_id', $partner_id)->first();
        $partner->delete();

        return redirect()->route('partner.index');
    }

    /**
     * Se editara el registro en la tabla de usuario o partner
     *
     * @param $id id de partner o usuario que se desea editar
     * @param $request['remember_token','name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender' ] son los datos ingresados para guardar el usuario
     * @param $request ['namePartner', 'document_partnerid', 'documentPartner', 'address', 'emailPartner', 'phonePartner', 'country_id', 'user_id' ] son los datos ingresados para guardar el partner
     * @return view va al index de partner
     */
    public function updatePartnerOrUser(Request $request, $id){

        if($request->submit == 'user'){

            $User=User::find($id);
            $User->remember_token = $request->_token;
            $User->name = $request->name;
            $User->lastName = $request->lastName;
            $User->document_id = $request->typeDocument;
            $User->document = strval($request->document);
            $User->email = $request->email;
            $User->password = bcrypt($request->password);
            $User->country_id = $request->country;
            $User->phone = strval($request->phone);
            $User->birthDate = $request->birthdate;
            $User->save();

        }elseif($request->submit == 'partner'){

            $partner = Partner::find($id);
            $partner->name = strtoupper($request->namePartner);
            $partner->document_id = $request->document_id;
            $partner->document = strval($request->documentPartner);
            $partner->address = strtoupper($request->address);
            $partner->email = $request->emailPartner;
            $partner->phone = strval($request->phonePartner);
            $partner->country_id = $request->country_id;

            $partner->save();
        }

        return redirect()->route('partner.index');
}

    /**
     * Se editara el status en la tabla de partner
     *
     * @param $request ['id', 'status'] son los datos ingresados para guardar el partner
     * @return back regresa a la vista de index
     */
    public function updateStatusPartner(Request $request){

        $partner = Partner::find($request->id);
            $partner->status = $request->status;

            $partner->save();

            return redirect()->route('partner.index');
    }

    /**
     * Se lista partner que el admin selecciono
     *
     * @param $id id del partner que se va a mostrar
     * @return view show de partner segun seleccione el admin
     * @return users usuarios del partner que selecciono el admin
     */
    public function showPartner($id){


        $partnerUser = PartnerUser::where('partner_id', $id)->first();
        $users = PartnerUser::where('partner_id', $id)->get();

        return view('admin.partners.showPartner', compact('partnerUser', 'users'));

    }

    /**
     * Se lista usuario del partner que el admin selecciono
     *
     * @param $id id del usuario que se va a mostrar
     * @return view show usuario del partner que selecciono el admin
     * @return $user  administradpr del partner seleccionado
     */
    public function showPartnerUser($id){

        $user=User::find($id);

        return view('admin.partners.showUserPartner', compact('user'));

    }


    /**
    * Es para crear un nuevo Usuario con ese partner
    * @param $request ['remember_token','name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender' ] son los datos ingresados para guardar el usuario
    * @return redirect a la view historyPartner
    */
    public function storeAddPartner(UserRequest $request){

        $User= new User();
        $User->remember_token = $request->_token;
        $User->name = $request->name;
        $User->lastName = $request->lastName;
        $User->document_id = $request->document_id;
        $User->document = strval($request->document);
        $User->email = $request->email;
        $User->password = bcrypt($request->password);
        $User->country_id = $request->country_id;
        $User->phone = strval($request->phone);
        $User->birthDate = $request->birthDay;
        $User->gender = $request->gender;

        //Guardando el nuevo objeto en la DB
        $User->save();

        //Agregar el nuevo usuario el role de user
        $User->attachRole(Role::where('name','partner')->first());



        //Creando la relacion del partner y un estudiante en la tabla pivote

        $relacion = new PartnerUser();
        $relacion->user_id = $User->id;
        $relacion->partner_id = $request->idPartner;

        //Guardando el objeto en la DB

        $relacion->save();

        $idPartner = $request->idPartner;


        return redirect()->route('partner.history', $idPartner);

    }


}
