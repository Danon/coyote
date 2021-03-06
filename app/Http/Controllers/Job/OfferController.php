<?php

namespace Coyote\Http\Controllers\Job;

use Coyote\Http\Controllers\Controller;
use Coyote\Http\Resources\AssetsResource;
use Coyote\Http\Resources\CommentCollection;
use Coyote\Http\Resources\FlagResource;
use Coyote\Repositories\Contracts\JobRepositoryInterface as JobRepository;
use Coyote\Firm;
use Coyote\Job;
use Coyote\Services\Elasticsearch\Builders\Job\MoreLikeThisBuilder;
use Coyote\Services\Flags;
use Coyote\Services\UrlBuilder;

class OfferController extends Controller
{
    /**
     * @var JobRepository
     */
    private $job;

    /**
     * @param JobRepository $job
     */
    public function __construct(JobRepository $job)
    {
        parent::__construct();

        $this->job = $job;
    }

    /**
     * @param \Coyote\Job $job
     * @return \Illuminate\View\View
     */
    public function index($job)
    {
        $this->breadcrumb->push('Praca', route('job.home'));
        $this->breadcrumb->push($job->title, UrlBuilder::job($job, true));

        $parser = app('parser.job');

        foreach (['description', 'requirements', 'recruitment'] as $name) {
            if (!empty($job->{$name})) {
                $job->{$name} = $parser->parse($job->{$name});
            }
        }

        if ($job->firm_id) {
            $job->firm->description = $parser->parse((string) $job->firm->description);
        }

        $job->addReferer(url()->previous());

        // search related offers
        $mlt = $this->job->search(new MoreLikeThisBuilder($job))->getSource();

        $comments = new CommentCollection($job->commentsWithChildren);
        $comments->job = $job;

        return $this->view('job.offer', [
            'rates_list'        => Job::getRatesList(),
            'employment_list'   => Job::getEmploymentList(),
            'employees_list'    => Firm::getEmployeesList(),
            'seniority_list'    => Job::getSeniorityList(),
            'subscribed'        => $this->userId ? $job->subscribers()->forUser($this->userId)->exists() : false,
            'is_applied'        => $job->applications()->forGuest($this->guestId)->exists(),
            'previous_url'      => $this->request->session()->get('current_url'),
            'payment'           => $this->userId === $job->user_id ? $job->getUnpaidPayment() : null,
            // tags along with grouped category
            'tags'              => $job->tags()->orderBy('priority', 'DESC')->with('category')->get()->groupCategory(),
            'comments'          => $comments->toArray($this->request),
            'applications'      => $this->applications($job),
            'flags'             => $this->flags(),
            'assets'            => AssetsResource::collection($job->firm->assets)->toArray($this->request)
        ])->with(
            compact('job', 'mlt')
        );
    }

    /**
     * @param Job $job
     * @return array|\Illuminate\Support\Collection
     */
    private function applications(Job $job)
    {
        if ($this->userId !== $job->user_id) {
            return [];
        }

        return $job->applications()->get();
    }

    private function flags()
    {
        $flags = resolve(Flags::class)->fromModels([Job::class, Job\Comment::class])->permission('job-delete')->get();

        return FlagResource::collection($flags)->toArray($this->request);
    }
}
