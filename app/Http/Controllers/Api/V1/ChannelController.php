<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;

class ChannelController extends Controller
{
    public function index()
    {
        return ChannelResource::collection(Channel::orderBy('name')->get());
    }
}
