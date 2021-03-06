<?php

namespace Coyote\Listeners;

ini_set('memory_limit', '10G');
set_time_limit(0);

use Coyote\Events\TopicWasDeleted;
use Coyote\Events\TopicWasMoved;
use Coyote\Events\TopicWasSaved;
use Coyote\Repositories\Contracts\TopicRepositoryInterface as TopicRepository;
use Coyote\Services\Elasticsearch\Crawler;
use Coyote\Topic;
use Illuminate\Contracts\Queue\ShouldQueue;

class TopicListener implements ShouldQueue
{
    /**
     * @var TopicRepository
     */
    protected $topic;

    /**
     * @var Crawler
     */
    private $crawler;

    /**
     * @param TopicRepository $topic
     * @param Crawler $crawler
     */
    public function __construct(TopicRepository $topic, Crawler $crawler)
    {
        $this->topic = $topic;
        $this->crawler = $crawler;
    }

    /**
     * @param TopicWasSaved $event
     */
    public function onTopicSave(TopicWasSaved $event)
    {
        $this->crawler->index($event->topic);
    }

    /**
     * @param TopicWasMoved $event
     */
    public function onTopicMove(TopicWasMoved $event)
    {
        $this->crawler->index($event->topic);
    }

    /**
     * @param TopicWasDeleted $event
     * @throws \Exception
     */
    public function onTopicDelete(TopicWasDeleted $event)
    {
        $topic = (new Topic)->forceFill($event->topic);

        $this->crawler->delete($topic);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'Coyote\Events\TopicWasSaved',
            'Coyote\Listeners\TopicListener@onTopicSave'
        );

        $events->listen(
            'Coyote\Events\TopicWasMoved',
            'Coyote\Listeners\TopicListener@onTopicMove'
        );

        $events->listen(
            'Coyote\Events\TopicWasDeleted',
            'Coyote\Listeners\TopicListener@onTopicDelete'
        );
    }
}
