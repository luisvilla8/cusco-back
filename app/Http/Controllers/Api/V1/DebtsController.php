<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Debt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DebtsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $debts = Debt::select(
            'debts.id',
            'agents.name as agent_name',
            'agents.categories as agent_categories',
            'debts.amount',
            'debts.created_at',
            'debts.updated_at'
        )
        ->join('agents', 'agents.id', '=', 'debts.agent_id')
        ->get();

        $debts->transform(function ($debt) {
            $debt->agent_categories = json_decode($debt->agent_categories);
            return $debt;
        });

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de deudas",
            "data" => $debts
        ], 200);
    }

    public static function getDebtsByAgentCategories(string $categoriesParam, int $agentTypeId)
    {
        return Debt::whereHas('agent', fn($query) =>
            $query->whereJsonContains('categories', explode(',', $categoriesParam))
                  ->where('agent_type_id', $agentTypeId)
        )->get();
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
        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $debt = Debt::create($validatedData);

        return response()->json([
            'success' => true,
            'data' => $debt,
            'message' => 'Debt record created successfully.',
        ], 201);
    }

    public static function saveAgentDebt($agentId, $amount)
    {
        $debt = Debt::where('agent_id', $agentId)->first();

        if ($debt) {
            $debt->amount += $amount;
            $debt->save();
        } else {
            $debt = Debt::create([
                'agent_id' => $agentId,
                'amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $debt;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Debts  $debts
     * @return \Illuminate\Http\Response
     */
    public function show(Debts $debts)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Debts  $debts
     * @return \Illuminate\Http\Response
     */
    public function edit(Debts $debts)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Debts  $debts
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Debts $debts)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Debts  $debts
     * @return \Illuminate\Http\Response
     */
    public function destroy(Debts $debts)
    {
        //
    }
}
