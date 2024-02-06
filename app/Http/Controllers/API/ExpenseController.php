<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Resources\ExpenseResource;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Notifications\ExpenseCreated;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $limit = (int)request()->get('limit', 10);

        $expenses = auth()->user()->expenses()->orderBy('created_at', 'desc')->paginate($limit);

        if (!$expenses) {
            return response()->noContent();
        }

        return ExpenseResource::collection($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseRequest $request): ExpenseResource
    {
        $validatedData = $request->validated();

        $this->authorize('create', Expense::class);

        // Associate the expense with the authenticated user.
        $validatedData['user_id'] = auth()->id();

        $expense = Expense::create($validatedData);

        auth()->user()->notify(new ExpenseCreated($expense));
        return new ExpenseResource($expense);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);
        return new ExpenseResource($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $validatedData = $request->validated();

        $this->authorize('update', $expense);

        $expense->update($validatedData);
        return new ExpenseResource($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): Response
    {
        $this->authorize('delete', $expense);
        $expense->delete();
        return response()->noContent();
    }
}
