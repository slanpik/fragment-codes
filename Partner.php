<?php

namespace App\Models\Partner;

use Illuminate\Database\Eloquent\Model;
use App\Models\Register\Country;
use App\Models\Register\Document;

class Partner extends Model
{
    protected $table = 'partners';

    protected $fillable = [
        'name', 'document_id', 'document', 'address', 'email', 'phone', 'country_id', 'status'
    ];

    /**
     * Funcion para la relacion MM con usuario
     * @return retorna el belongstomany de un usuario
     */
       public function users()
       {
            return $this->belongsToMany('App\Models\User', 'partner_user','partner_id', 'user_id');
      }

     /**
     * Funcion para capturar el nombre del pais
     * @var country es el objeto que trae despues de buscar en la bd
     * @return retorna el nombre del pais
     */
      public function getCountry($country_id)
    {
        $country = Country::find($country_id);
        return $country->name;
    }

    /**
     * Funcion para la relacion belongsto con country
     * @return retorna el belongsto country
     */
    public function countrys(){
        return $this->belongsTo('App\Models\Register\Country','country_id');
    }

    /**
     * Funcion para la relacion belongsto con document
     * @return retorna el belongsto document
     */
    public function documents(){
        return $this->belongsTo('App\Models\Register\Document','document_id');
    }


    /**
     * Funcion para capturar el tipo de documento
     * @var document es el objeto que trae despues de buscar en la bd
     * @return retorna el tipo de documento que tiene un partner
     */
    public function getDocument($document_id)
    {
        $document = Document::find($document_id);
        return $document->typeDocument;
    }

    /**
    * Función para realizar la busqueda con la base de datos con un Scope usando la convencion de laravel
    * @var $query es la variable que recibe para la busqueda en la bd
    * @var $name es la variable que recibe para realizar la busqueda como un parametro necesario
    */
    public function ScopeName($query, $name)
    {
        if(trim($name) != ''){
        $query->where('name','ILIKE','%'.$name.'%')->orWhere('document','ILIKE','%'.$name.'%');
        }
    }
}
