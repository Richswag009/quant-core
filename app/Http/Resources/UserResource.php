<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Hamcrest\Type\IsBoolean;
use Illuminate\Http\Middleware\TrustProxies;

class UserResource extends JsonResource
{
    public bool $show_token;

    public function __construct($resource, bool $show_token = false)
    {
        $this->show_token = $show_token;
        parent::__construct($resource);
    }


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'role' => $this->role,
            'created_at' => $this->created_at,
            $this->mergeWhen($this->show_token, [
                'token' => $this->createToken('API Token', ['*'], Carbon::now()->addDays(7))->plainTextToken
            ]),
        ];
    }
}
