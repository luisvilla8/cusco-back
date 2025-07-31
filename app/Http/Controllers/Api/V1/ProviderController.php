<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\AgentController as AgentV1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    private int $agentType = 2;
    private Controller $agentController;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->agentController = new AgentV1();
        $providers = $this->agentController->index($this->agentType);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de proveedores",
            "data" => $providers,
        ], 200);
    }
}
