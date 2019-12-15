<?php

namespace Coyote\Http\Resources;

use Coyote\Pm;
use Coyote\Services\Media\Factory;
use Coyote\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $text
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $read_at
 * @property \Coyote\User $author
 * @property int $folder
 */
class PmResource extends JsonResource
{
    /**
     * @var \Coyote\Services\Parser\Factories\PmFactory
     */
    private static $parser = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $only = $this->resource->only(['id', 'folder']);

        $text = $this->parse($this->text instanceof Pm\Text ? $this->text->text : $this->text);

        return array_merge($only, [
            'url'                   => route('user.pm.show', [$this->id]),
            'created_at'            => format_date($this->created_at),
            'excerpt'               => excerpt($text, 50),
            'text'                  => $text,
            'read_at'               => $this->read_at ? format_date($this->read_at) : null,
            'user'                  => new UserResource($this->author)
        ]);
    }

    /**
     * @param string $text
     * @return string
     */
    private function parse(string $text): string
    {
        if (self::$parser === null) {
            self::$parser = app('parser.pm');
        }

        return self::$parser->parse($text);
    }
}
