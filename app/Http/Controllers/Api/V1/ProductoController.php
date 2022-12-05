<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Producto;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;


class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Producto::all();
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de productos",
            "data" => $products
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'descripcion' => 'string',
            'id_tipo_medida' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $descripcion = $request->descripcion ? $request->descripcion : "";

        $producto = Producto::create([
            "nombre" => $request->nombre,
            "descripcion" => $descripcion,
            "id_tipo_medida" => $request->id_tipo_medida,
            "cantidad" => 0,
            "costo" => 0,
            "precio" => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $producto->save();
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de producto exitoso!",
            "data" => $producto
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $producto = Producto::find($id);
        if ($producto) {
            return response()->json([
                "msg" => 'Producto encontrado',
                "data" => $producto
            ], 200);
        }

        return response()->json([
            "msg" => 'Producto no encontrado',
            "data" => null
        ], 404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Producto  $producto
     * @return \Illuminate\Http\Response
     */
    public function edit(Producto $producto)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);
        $producto->nombre = $request->nombre ?? $producto->nombre;
        $producto->id_tipo_medida = $request->id_tipo_medida ?? $producto->id_tipo_medida;
        $producto->descripcion = $request->descripcion ?? $producto->descripcion;
        $producto->cantidad = $request->cantidad ?? $producto->cantidad;
        $producto->costo = $request->costo ?? $producto->costo;
        $producto->precio = $request->precio ?? $producto->precio;
        $producto->save();

        if ($producto) {
            return response()->json([
                "msg" => 'Producto actualizado satisfactoriamente',
                "data" => $producto
            ], 200);
        }

        return response()->json([
            "msg" => 'Producto no encontrado',
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $producto = Producto::find($id);
        if ($producto) {
            $producto->delete();
            return response()->json([
                "msg" => 'Producto eliminado satisfactoriamente'
            ], 200);
        }
        return response()->json([
            "msg" => 'Producto no encontrado',
        ], 404);
    }
}
