<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array with both camelCase and snake_case properties
     * for full backwards-compatibility with frontend and legacy clients.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            '_id' => (string) $this->id,
            'username' => $this->username,
            'name' => $this->name ?: $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'minecraftUsername' => $this->minecraft_username ?? '',
            'minecraft_username' => $this->minecraft_username ?? '',
            'minecraftUuid' => $this->minecraft_uuid ?? '',
            'minecraft_uuid' => $this->minecraft_uuid ?? '',
            'isGameLinked' => !empty($this->minecraft_uuid),
            'is_game_linked' => !empty($this->minecraft_uuid),
            'gameLinkedAt' => $this->game_linked_at ? $this->game_linked_at->toISOString() : null,
            'discordId' => $this->discord_id ?? '',
            'discord_id' => $this->discord_id ?? '',
            'discordUsername' => $this->discord_username ?? '',
            'discord_username' => $this->discord_username ?? '',
            'bio' => $this->bio ?? '',
            'avatar' => $this->avatar ?? '',
            'skinUrl' => $this->skin_url ?? '',
            'skin_url' => $this->skin_url ?? '',
            'coins' => (int) $this->coins,
            'createdAt' => $this->created_at ? $this->created_at->toISOString() : null,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }
}
