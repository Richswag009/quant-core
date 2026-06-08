<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBatchRequest;
use App\Http\Requests\RejectBatchRequest;
use App\Http\Resources\BatchItemResource;
use App\Services\Batch\BatchService;
use Illuminate\Http\Request;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Services\Batch\BatchValidationService;
use Illuminate\Database\Eloquent\Collection;

class BatchController extends Controller
{

    public function __construct(
        protected BatchService $batchService
    ) {}


    public function createBatch(CreateBatchRequest $request)
    {
        try {
            // Validate the request data
            $validated = $request->validated();

            $batch = $this->batchService->createBatch($validated, $request->file('file'));

            return $this->createdResponse('batch created successfully', new BatchResource($batch));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function getAllBatches(Request $request)
    {
        try {

            $filters = $request->only(['status', 'source', 'from', 'to']);
            $per_page =  $request->get('per_page', 20);

            $batches = $this->batchService->getAllBatches($filters, $per_page);

            return $this->okResponse('fetched all batches successfully', BatchResource::collection($batches));
        } catch (\Exception $th) {
            return $this->serverErrorResponse("something went wrong");
        }
    }

    public function getBatch(Batch $batch)
    {
        try {
            $user = auth()->user();

            if ($user->role === 'operator' && $batch->created_by !== $user->id) {
                return $this->forbiddenResponse("You do not have access to this batch");
            }
            $batch = $batch->load(["creator", 'approver']);

            return $this->okResponse('fetched batch successfully', new BatchResource($batch));
        } catch (\Exception $th) {
            return $this->serverErrorResponse("something went wrong");
        }
    }
    public function getBatchItems(Batch $batch)
    {
        try {

            $items = $batch->items()
                ->filter(request()->only(['status', 'beneficiary_name']))
                ->paginate(20);

            return $this->okResponse('fetched batch successfully', BatchItemResource::collection($items));
        } catch (\Exception $th) {
            return $this->serverErrorResponse("something went wrong");
        }
    }


    public function validateBatch(Batch $batch, BatchValidationService $validationService)
    {
        $result = $validationService->validateBatch($batch);

        return $this->okResponse('Batch validated successfully', $result);
    }


    public function submitBatch(Batch $batch, BatchService $batchService)
    {

        $batchService->submitBatch($batch);
        return $this->okResponse('Batch submitted successfully', new BatchResource($batch->load('items')));
    }

    public function approveBatch(Batch $batch, BatchService $batchService)
    {
        $batchService->approveBatch($batch);
        return $this->okResponse('Batch approved successfully', new BatchResource($batch->load('items')));
    }


    public function rejectBatch(Batch $batch, RejectBatchRequest $request, BatchService $batchService)
    {

        $validated = $request->validated();
        $batchService->rejectBatch($batch, $validated['reason']);
        return $this->okResponse('Batch rejected successfully', new BatchResource($batch->load('items')));
    }

    public function postBatch(Batch $batch, BatchService $batchService)
    {
        $batchService->postBatch($batch);
        return $this->createdResponse('Batch posting initiated', new BatchResource($batch->load('items')));
    }


    public function retryBatch(Batch $batch, BatchService $batchService)
    {
        $batchService->retryBatch($batch);
        return $this->createdResponse('Batch posting initiated', new BatchResource($batch->load('items')));
    }
}
