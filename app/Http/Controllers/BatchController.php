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



    public function createBatch(CreateBatchRequest $request, BatchService $batchService)
    {
        try {
            $user = $request->user();

            // Validate the request data
            $validated = $request->validated();

            $batch = $batchService->createBatch($validated, $request->file('file'));

            return $this->okResponse('login successfully', new BatchResource($batch));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function getAllBatches(Request $request)
    {
        try {
            $user = $request->user();

            $batches = $user->batches()
                ->filter($request->only(['status', 'source', 'from', 'to']))
                ->with('items', "creator")
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return $this->okResponse('fetched all batches successfully', BatchResource::collection($batches));
        } catch (\Exception $th) {
            return $this->serverErrorResponse("something went wrong");
        }
    }

    public function getBatch(Batch $batch)
    {
        try {
            $user = auth()->user();

            return $this->okResponse('fetched batch successfully', new BatchResource($batch->load("creator")));
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
        try {

            $result = $validationService->validateBatch($batch);

            return $this->okResponse('Batch validated successfully', $result);
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function submitBatch(Batch $batch, BatchService $batchService)
    {
        try {

            $batchService->submitBatch($batch);
            return $this->okResponse('Batch submitted successfully', new BatchResource($batch->load('items')));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function approveBatch(Batch $batch, BatchService $batchService)
    {
        try {

            $batchService->approveBatch($batch);
            return $this->okResponse('Batch approved successfully', new BatchResource($batch->load('items')));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }


    public function rejectBatch(Batch $batch, RejectBatchRequest $request, BatchService $batchService)
    {
        try {
            $batchService->rejectBatch($batch, $request->reason);
            return $this->okResponse('Batch rejected successfully', new BatchResource($batch->load('items')));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function postBatch(Batch $batch, BatchService $batchService)
    {
        try {
            $batchService->postBatch($batch);
            return $this->createdResponse('Batch posting initiated', new BatchResource($batch->load('items')));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    public function retryBatch(Batch $batch, BatchService $batchService)
    {
        try {
            $user = auth()->user();
            $batchService->retryBatch($batch);
            return $this->createdResponse('Batch posting initiated', new BatchResource($batch->load('items')));
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }
}
