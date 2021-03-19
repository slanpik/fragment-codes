<?php

namespace App\Http\Controllers\Teacher;

use App\Role;
use App\Models\User;
use App\Models\Exam\Exam;
use App\Models\User\Exam_User;
use App\Models\Register\Country;
use App\Models\Register\Document;
use App\Models\Material\Material;
use App\Models\Material\MaterialModule;
use App\Models\Material\Module;
use App\Models\Material\ModulePage;
use App\Models\Material\Page;
use App\Models\Material\MaterialUser;
use App\Http\Requests\Admin\Teacher\TeacherRequest;
use App\Http\Requests\Admin\Teacher\ExamTeacherRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

use Carbon\Carbon;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TeacherController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:teacher']);
    }

    /**
     * sirve para listar los profesores que existen en la plataforma
     * 
     * @return view retorna la vista del index teacher
     * @return $teachers son todos los profesosres  que se encuentran en la plataforma
     */
    public function index(Request $request)
    {
        //se hace una consulta para traer todos los usuarios del rol teacher
        $userTeachers = Role::find(6)->users;

        //Valida si entro datos para filtrar la consulta
        if (!is_null($request->name) || !is_null($request->lastName) || !is_null($request->document) || !is_null($request->email)) {
            $filtrado = true;
            $teachers = User::when(!is_null($request->name), function ($query) use ($request) {
                return $query->where('id', '=', $request->name);
            })->when(!is_null($request->lastName), function ($query) use ($request) {
                return $query->where('id', '=', $request->lastName);
            })->when(!is_null($request->document), function ($query) use ($request) {
                return $query->where('id', '=', $request->document);
            })->when(!is_null($request->email), function ($query) use ($request) {
                return $query->where('id', '=', $request->email);
            })->first();
        } else {
            //consulta todos los profesores que posee certmind
            $filtrado = false;
            $teachers = Role::find(6)->users()->orderBy('name')->orderBy('lastName')->paginate(6);
        }

        return view('admin.teachers.indexTeachers', compact('teachers', 'userTeachers', 'filtrado'));
    }

    /**
     * Sirve llevar al usuario a la vista con el formulario de creacion del Profesor
     * 
     * @return view me envia a la vista de crear profesor
     * @return $countrys me envia todos los paises
     * @return $documents envia todos los tipos de documentos
     */
    public function create()
    {
        $countrys = Country::all();
        $documents = Document::all();

        return view('admin.teachers.createTeachers', compact('countrys', 'documents'));
    }

    /**
     * es el que me permite crear un nuevo usuario profesor en la plataforma
     * 
     * @param $request ['name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender', 'score']
     * @return redirect me lleva  al index de students
     */
    public function save(TeacherRequest $request)
    {

        User::saveTeacher($request);

        return redirect(route('teacher.index'));
    }

    /**
     * Sirve para editar el profesor seleccionado
     * 
     * @param $idStudent es la id del profesor que se desea validar
     * 
     * @return view me lleva a la vista de editar profesor
     * @return $teacher se regresa el profesor 
     * @return $countrys retorna todos los paises que se tiene en la base de datos
     * @return $documents retorna todos los tipos de documentos que existen
     * @return back regresa a la vista donde estaba si no encuentra el registro
     */
    public function edit($idTeacher)
    {
        $teacher = User::find($idTeacher);

        //valida que el usuario exista y sea teacher
        if ((!$teacher) || ($teacher->getRole($idTeacher) != 6)) {
            return back();
        }

        $countrys = Country::all();
        $documents = Document::all();

        return view('admin.teachers.editTeachers', compact('teacher', 'countrys', 'documents'));
    }

    /**
     * sirve para actualizar el registro del profesor
     * 
     * @param $request  ['name','lastName', 'document_id', 'document','email', 'password', 'country_id', 'phone', 'birthDate', 'gender', 'score']
     * @param $idStudent es la id del profesor al que se quiere editar
     * 
     * @return redirect me redirecciona al index de teachers
     */
    public function update(TeacherRequest $request, $idTeacher)
    {
        User::saveTeacher($request, $idTeacher);

        return redirect(route('teacher.index'));
    }

    /**
     * sirve para eliminar el profesor
     * 
     * @param $idStudent es la id del profesor
     * 
     * @return back sirve para retornar a la vista dfonde se encontraba
     */
    public function delete($idTeacher)
    {
        $teacher = User::find($idTeacher);

        //valida que el usuario exista y sea teacher
        if ((!$teacher) || ($teacher->getRole($idTeacher) != 6)) {
            return back();
        }

        $teacher->delete();
        return back();
    }

    /**
     * Muestra la informacion basica del estudiante
     * 
     * @param $idteacher es la id del estudiante al cual se desea entrar
     * 
     * @return view me envia la vista del show de estudent
     * @return $teacher me envia el profesor
     * @return $selectExams son los examenes que pueden agendarse
     * @return $fechaActual para las validaciones de los campos date
     */
    public function show($idTeacher)
    {
        $exams = Exam::where('confirmed', 1)->get();
        $teacher = User::find($idTeacher);

        //valida que el usuario exista y sea teacher
        if ((!$teacher) || ($teacher->getRole($idTeacher) != 6)) {
            return back();
        }

        $this->validateExam($teacher);

        $tempUserExam = array();
        foreach ($teacher->userExams as $userExam) {
            if (is_null($userExam->result)) {
                $tempUserExam[] = $userExam->exam_id;
            }
        }

        $selectExams = array();
        foreach ($exams as $exam) {
            if (!in_array($exam->id, $tempUserExam)) {
                $selectExams[] = $exam;
            }
        }

        $teacher->setRelation('userExams', $teacher->userExams()->orderBy('id', 'DESC')->paginate(5));

        //sirve para validar la fecha en los formularios de agendar examen
        $fechaActual = date('Y-m-d');

        return view('admin.teachers.showTeachers', compact('teacher', 'selectExams', 'fechaActual'));
    }

    /**
     * sirve para guardar el examen al teacher
     * 
     * @param $idteacher es la id del profesor a la que se le va agregar el examen
     * @param $request ['startDate', 'startTime', 'id_exam']
     * 
     * @return back cuando la validacion no esta correcta
     * @return redirect envia a show de profesor al que pertenece el examen
     */
    public function saveTeacherExam($idTeacher, ExamTeacherRequest $request)
    {
        //cuando la fecha y hora son menores a la actual
        if ($this->validityTime(new Carbon($request->startDate . ' ' . $request->startTime), false)) {
            return back();
        }

        //valida que no envie vacio el select de exam
        if ($request->id_exam == 'null') {
            return back();
        }

        //crea un nuevo examen y lo guarda
        $examUser = new Exam_User();

        $examUser->users_id = $idTeacher;
        $examUser->exam_id = $request->id_exam;
        $examUser->startTimeExam = new Carbon($request->startDate . ' ' . $request->startTime);
        $examUser->point = 0;

        $examUser->save();

        return redirect(route('teacher.show', $idTeacher));
    }

    /**
     * sirve para editar un examen programado
     * 
     * @param $idExamUser es la id de la tabla pivote del examen asignado
     * @param $request ['startDate', 'startTime', 'id_exam']
     * 
     * @return back retorna a la vista donde se encontraba
     * @return redirect me envia al show del profesor
     */
    public function saveTeacherExamEdit($idExamUser, ExamTeacherRequest $request)
    {
        //cuando la fecha y hora son menores a la actual
        if ($this->validityTime(new Carbon($request->startDate . ' ' . $request->startTime), false)) {
            return back();
        }

        //valida que no envie vacio el select de exam
        if ($request->id_exam == 'null') {
            return back();
        }

        $examUser = Exam_User::find($idExamUser);

        $examUser->startTimeExam = new Carbon($request->startDate . ' ' . $request->startTime);

        $examUser->save();

        return redirect(route('teacher.show', $examUser->user->id));
    }

    /**
     * elimina un examen programado
     * 
     * @param $idExamUser es la id de la tabla pivote donde esta almacenado el usuario
     * 
     * @return back me retorna a la vista donde se e4ncontraba
     */
    public function deleteTeacherExam($idExamUser)
    {

        $examUser = Exam_User::find($idExamUser);

        //valida que el usuario exista y sea teacher
        if (!$examUser) {
            return back();
        }

        $examUser->delete();
        return back();
    }

    /********************************
     * Validacion para determinar
     * si el examen ya paso el tiempo,
     * y el usuario no lo a presentado 
     *********************************/

    /**
    * Sirve para validar todos los examenes que posee el Profesor y revisa si el estudiante ya se paso del tiempo
    *
    * @param $teacher recibe un objeto estudiante
    */
    public function validateExam($teacher)
    {
        foreach ($teacher->userExams as $userExam) {

            if (!$userExam->endTimeExam) {
                $timeoutExam = $this->newEndTimeExam($userExam->startTimeExam, $userExam->exam->duration);

                //valida si el exmen ya se paso del tiempo establecido mas la adicion del tiempo que posee el examen
                if ($this->validityTime($timeoutExam, false)) {
                    //da por perdido el examen
                    $userExam->active = 1;
                    $userExam->result = 0;
                    $userExam->endTimeExam = $timeoutExam;

                    $userExam->save();
                }
            }
        }
    }

    /**
    * hace sumatoria de una fecha y hora en minutos
    *
    * @param $startTime se requere una fecha
    * @param $duration cantidad en entero de minutos que desee sumatoria
    *
    * @return $endTime retorna la sumatoria completa
    */
    public function newEndTimeExam($startTime, $duration)
    {
        $startTime = new Carbon($startTime);

        return $startTime->addMinute($duration);
    }

    /**
    * Valida si la hora ingresada aun no a pasado con la hora actual del servidor
    *
    * @param $dateTime fecha que desea comparar con la hora $timeActual
    * @param $higher determina si desea la hora es mayor o menor
    *
    * @return true or False validando si la hora ingresada es mayor o menor a la hora actual
    */
    public function validityTime($dateTime, $higher = true)
    {
        $timeActually = Carbon::now();

        if ($higher) {
            //compara si la fecha ingresada es mayor a la hora actual
            if ($dateTime >= $timeActually) {
                //el tiempo no se a cumplido
                return true;
            }
            //ya paso el tiempo
            return false;
        }

        //compara si la fecha ingresada es menor a la hora actual
        if ($dateTime <= $timeActually) {

            return true;
        }

        return false;
    }

    /** 
    *
    * @param $request trae el id del usuario,$materials trae todos los  materiales    
    *
    * @return los materiales del profesor que esta logueado 
    */
    public function indexMaterial(Request $request)
    {

        /** @var User trae el ID del usuario logueado  */
        $user = User::find(auth()->user()->id);

        /** Identifica los materiales que le pertenecen al profesor */
        $materials = MaterialUser::where('user_id', auth()->user()->id)->orderBy('id', 'DESC')->get();

        /** @var Materiales Guarda el fitro del profesor */
        $losMateriales = [];
        $i = 0;
        //  filtra los materiales que le pertenecen al  profesor 
        foreach ($materials as  $value) {
            $temporal = Material::find($value->material_id);
            $losMateriales[$i++] = $temporal;
        }
        $materials = $losMateriales;


        return view('teachers.materialsTeachers.index', compact('materials', 'user'));
    }


    /** 
    *
    * @param $id trae el id del  material al que se desea acceder
    *
    * @return retorna la vista del material 
    */
    public function showMaterial($id)
    { 
        /** @var Material consulta el id  del material quye se desa acceder  */
        $material = Material::where('id', $id)->firstOrFail();

        $modules = [];
        
        // Recorro todos los modules 
        foreach ($material->modules as $module) {
          //en el array se agrega un objeto
          array_push($modules,$module);   
          
          //almacena todas la  paginas del modulo que se acaba de agregar
          $pages = [];
    
           //en el array se agrega todas las paginas del modulo en un array
          foreach ($module->pages as $page) {
            array_push($pages,$page);      
          }
    
          // Cuando haya algo en "$pages" ordeno
          if($pages != null){
            // Ordena las paginas por nombre
            usort($pages,array($this,"cmp") );
            // Asigno el arreglo ordenado
            $module->pages = $pages;
          }
     
        }
      // Cuando haya algo en "$modules" ordeno
        if($modules != null){
          // Ordena las modules por nombre
          usort($modules,array($this,"cmp") );
    
        }
    
    
      
        return view('teachers.materialsTeachers.show', compact('material','modules'));
      }
      
      public function cmp($a, $b)
    {
        return strcmp($a->name, $b->name);
    }

    /** 
    *
    * @param $idMaterial trae el id del  material al que pertenece el module
    *@param $idModule Trae id del modulo al que pertenece el page
    *@param $id tre el id del page
    *
    * @return retorna los  modulos y pages qu pertenecen al material
    */

    public function showPage($idMaterial, $idModule, $id)
    {
        /** @var Page consulta el id del page   */
        $page = Page::where('id', '=', $id)->firstOrFail();
        /** @Material guarda  el id del material  en IdMaterial para enviarlo  a la vista */
        $material = Material::find($idMaterial);
        /** @MOdule guarda  el id del module en IdModule para enviarlo  a la vista */
        $module = Module::find($idModule);




        return view('teachers.materialsTeachers.pages.show', compact('idMaterial', 'idModule',  'page', 'material'));
    }
}
