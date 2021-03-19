<?php

namespace App\Http\Controllers\v1\Project;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

use App\Models\Project\Project;
use App\Models\Project\Component;

use App\Transformers\ComponentTransformer;

class ComponentsController extends ApiController
{
    public function __construct()
    {
        $this->middleware('transform.input:'.ComponentTransformer::class)->only(['store', 'update']);
    }

    /**
     * Lista todos los componenetes que posee un proyecto
     *
     * @param Project El proyecto que desea ver los componentes
     *
     * @return Component envia todos los componentes
     */
    public function index(Project $project)
    {
        $components = $project->components;

        return $this->showAll($components);
    }

    /**
     * Crea un componente para el proyecto
     *
     * @param Request ['nombre', 'color', 'icono']
     * @param Project Es el proyecto a donde se le va a añadir el componente
     *
     * @return Component el componente creado
     */
    public function store(Request $request, Project $project)
    {
        $rules = [
            'name' => 'required|max:200|string',
            'color' => 'required|max:15|string',
            'description' => 'max:200|string|nullable'
        ];

        $this->validate($request, $rules);

        $campos = $request->all();

        $component = Component::create($campos);

        return $this->showOne($component, 201);
    }

    /**
     * Sirve para mostrar un components de un proyecto determinado
     *
     * @param  Project
     * @param Component
     *
     * @return Component
     */
    public function show(Project $project, Component $component)
    {
        if($project->id != $component->project_id){
            //error id del usuario no coincide con el registro
            return $this->errorResponse('El proyecto no coincide con el registro', 409);
        }

        return $this->showOne($component);
    }

    /**
     * Sirve para editar un componente en especifico
     *
     * @param  Project
     * @param  Component
     * @param Request ['nombre', 'color', 'icono']
     *
     * @return Component
     */
    public function update(Request $request, Project $project, Component $component)
    {
        if($request->project_id != $component->project_id){
            //error id del usuario no coincide con el registro
            return $this->errorResponse('El proyecto no coincide con el registro', 409);
        }

        if($request->has('name')){
            $component->name = $request->name;
        }

        if($request->has('color')){
            $component->color = $request->color;
        }

        if($request->has('description')){
            $component->description = $request->description;
        }

        if(! $component->isDirty()){
            //debe tener un algin cambio o valor distinto
            return $this->errorResponse('Se debe especificar un valor diferente para actualizar', 422);
        }
		
		$component->save();

        return $this->showOne($component);
    }

    /**
     * Sirve para eliminar los componentes de un proyecto
     *
     * @param  Project
     * @param  Component
     *
     * @return Component Elimina el componente que se elimino
     */
    public function destroy(Project $project, Component $component)
    {
        if($project->id != $component->project_id){
            //error id del usuario no coincide con el registro
            return $this->errorResponse('El proyecto no coincide con el registro', 409);
        }

        $component->delete();

        return $this->showOne($component);
    }
}
