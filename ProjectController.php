<?php

namespace App\Http\Controllers\v1\Project;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\Project\Project;
use App\Models\User\User;
use App\Transformers\ProjectTransformer;
use App\Models\User\ProjectUser;
Use App\Models\Shared\Avatar;
use App\Models\User\ProjectCategory;

class ProjectController extends ApiController
{
    public function __construct()
    {
        $this->middleware('transform.input:'.ProjectTransformer::class)->only(['store', 'update']);
    }

    /**
     * Retorna todos los projectos del usuario
     * @return \Illuminate\Http\Response
     */
    public function index(User $user)
    {
        $projects = Project::where('owner_id', $user->id)->get();
        return $this->showAll($projects);
    }

    /**
     * Crear un proyecto
     *
     * @param User $user Recibe el usuario que crea el proyecto
     * @param Request $request Recibe el formulario para crear el proyecto
     * @return \Illuminate\Http\Response
     */
    public function store(User $user, Request $request)
    {
        $rules = [
            'title' =>'required|string',
            'description' =>'max:250|string|nullable',
            'color' => 'required|max:10|string',
            'start_date' => 'date',
            'end_date' => 'date|nullable',
            'status_id' => 'required|numeric',
            'avatar_id' => 'required|image',
            'budget' => 'numeric|nullable'
        ];

        $this->validate($request, $rules);
        $data = $request->all();

        /**
         * Seccion para crear/Guardar el avatar subido
        */
        // El archivo recibido
        $file = $request->File('avatar_id');

        //nombre del archivo 
        $file_name = $file->getClientOriginalName();
        $file_name = time().$file_name;
        $file_name = strtolower($file_name);
        
        //se guarda en el archivo local
        \Storage::disk('avatar')->put($file_name, \File::get($file));

        // Creo el nuevo avatar
        $avatar = Avatar::storeNewAvatar($file_name);

        /*
         * Guardo el nuevo proyecto en la tabla 'owner_project_categories' tambien
         * ya que toca llenar esa tabla tambien con estos datos
        */
        $ownerProjectCategory = ProjectCategory::storeNewProjectCategory($user->id);

        /**
         * ► Creo el proyecto ◄
        */
        $data['owner_project_category_id'] = $ownerProjectCategory->id;
        $data['avatar_id'] = $avatar->id;
        $data['owner_id'] = $user->id;
        $project = Project::create($data);

        /*
         * Guardo el nuevo proyecto en la tabla 'projectUser' tambien
         * ya que toca llenar esa tabla tambien con estos datos
        */
        ProjectUser::storeNewProjectUser($project->id, $project->owner_id);

        return $this->showOne($project, 201);
    }

    /**
     * Obtiene el proyecto de un usuario
     * @return \Illuminate\Http\Response
     */
    public function show(User $user, Project $project)
    {
        // valida que el proyecto le pertenezca a elusuario
        if($project->owner_id != $user->id){
            return  $this->errorResponse('El proyecto no le pertenece a ese usuario',  409);
        }
        return $this->showOne($project);
    }

    /**
     * Actializa el registro del proyecto pedido
     *
     * @param  Request $request recibe el formulario
     * @param  User $user recibe el usuario a actualizar el proyecto
     * @param  Project $project recibe el proyecto a actualizar
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user, Project $project)
    {

        $rules = [
            'title' =>'string',
            'description' =>'max:250|string',
            'color' => 'max:10|string',
            'start_date' => 'date',
            'end_date' => 'date',
            'privacity' => 'boolean',
            'status_id' => 'numeric',
            'avatar_id' => 'image',
            'owner_project_category_id' => 'numeric',
            'budget' => 'numeric'
        ];

        $this->validate($request, $rules);

        if($project->owner_id == $user->id){
            if($request->has('title')){
                $project->title = $request->title;
            }
            if($request->has('description')){
                $project->description = $request->description;
            }
            if($request->has('color')){
                $project->color = $request->color;
            }
            if($request->has('start_date')){
                $project->start_date = $request->start_date;
            }
            if($request->has('end_date')){
                $project->end_date = $request->end_date;
            }
            if($request->has('privacity')){
                $project->privacity = $request->privacity;
            }
            if($request->has('status_id')){
                $project->status_id = $request->status_id;
            }
            if($request->has('owner_project_category_id')){
                $project->owner_project_category_id = $request->owner_project_category_id;
            }

            if($request->has('budget')){
                $project->budget = $request->budget;
            }

            if($request->hasFile('avatar_id')){
                //El archivo recibido
                $file = $request->File('avatar_id');
                //Nombre del archivo recibido
                $name = time().$file->getClientOriginalName();

                $file->move(public_path().'/avatars/',$name);
                // Le doy la direccion de donde fue colocada el avatar
                $avatarData['image_path'] = public_path().'/avatars/'.$name;
                // Creo el nuevo avatar
                $avatar = Avatar::create($avatarData);

                //Asigno el nuevo avatar id
                $project->$avatar->id;
            }

            //En caso que que no halla ningun cambio
            if(! $project->isDirty()){
                return  $this->errorResponse('Se debe especificar un valor diferente para actualizar', 422);
            }
            $project->save();
            return $this->showOne($project);
        }
        return  $this->errorResponse('El usuario no es dueño de este proyecto',  409);
    }

    /**
     * Elimina el proyecto que el owner selecciono
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user, Project $project)
    {
        // valida que el proyecto le pertenezca a elusuario
        if($project->owner_id != $user->id){
            return  $this->errorResponse('El proyecto no le pertenece a ese usuario', 409);
        }
        $project->delete();
        return $this->showOne($project);
    }

}
