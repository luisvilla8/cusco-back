<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\AgentController;
use App\Util\Constants\TransactionsConstants;

class ClientController extends Controller
{
    private int $agentType = 1;
    private Controller $agentController;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categoriesParam = "";

        $clients = AgentController::getAgentsByCategory($categoriesParam, TransactionsConstants::AGENT_TYPE_CLIENT);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de clientes",
            "data" => $clients,
        ], 200);
    }

    public function getClientsByCategories(Request $request)
    {
        $categoriesParam = $request->categories;

        $clients = AgentController::getAgentsByCategory($categoriesParam, TransactionsConstants::AGENT_TYPE_CLIENT);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de clientes",
            "data" => $clients,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $this->agentController = new AgentController();

        $this->agentController->update($request, $id);

        $clients = $this->agentController->index($this->agentType);

        return response()->json([
            "status" => 1,
            "msg" => "Cliente actualizado satisfactoriamente",
            "data" => $clients,
        ], 200);
    }

    public function store(Request $request)
    {
        $this->agentController = new AgentController();

        $this->agentController->store($request, TransactionsConstants::AGENT_TYPE_CLIENT);

        $clients = $this->agentController->index(TransactionsConstants::AGENT_TYPE_CLIENT);

        return response()->json([
            "status" => 1,
            "msg" => "Cliente agregado satisfactoriamente",
            "data" => $clients,
        ], 200);
    }
}
