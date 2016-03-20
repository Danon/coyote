<?php

namespace Coyote\Http\Controllers\Job;

use Carbon\Carbon;
use Coyote\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Coyote\Repositories\Contracts\FirmRepositoryInterface;
use Coyote\Repositories\Contracts\JobRepositoryInterface ;
use Coyote\Firm;
use Coyote\Job;

class OfferController extends Controller
{
    /**
     * @var JobRepositoryInterface
     */
    private $job;

    /**
     * @var FirmRepositoryInterface
     */
    private $firm;

    /**
     * OfferController constructor.
     * @param JobRepositoryInterface $job
     * @param FirmRepositoryInterface $firm
     */
    public function __construct(JobRepositoryInterface $job, FirmRepositoryInterface $firm)
    {
        parent::__construct();

        $this->job = $job;
        $this->firm = $firm;
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function index(Request $request, $id)
    {
        $job = $this->job->findById($id);

        $this->breadcrumb->push('Praca', route('job.home'));
        $this->breadcrumb->push($job->title, route('job.offer', [$job->id, $job->path]));

        $parser = app('Parser\Job');

        foreach (['description', 'requirements', 'recruitment'] as $name) {
            if (!empty($job->$name)) {
                $job->$name = $parser->parse($job->$name);
            }
        }

        $firm = [];
        if ($job->firm_id) {
            $firm = $this->firm->find($job->firm_id);
            $firm->description = $parser->parse($firm->description);
        }

        $job->increment('visits');

        // @todo w laravel 5.2 mozna uzyc:
        // url()->previous();
        if ($request->header('referer')) {
            $referer = $job->referers()->firstOrNew(['url' => $request->header('referer')]);
            $referer->increment('count');
        }

        return $this->view('job.offer', [
            'ratesList'         => Job::getRatesList(),
            'employmentList'    => Job::getEmploymentList(),
            'employeesList'     => Firm::getEmployeesList(),
            'deadline'          => Carbon::parse($job->deadline_at)->diff(Carbon::now())->days
        ])->with(
            compact('job', 'firm')
        );
    }
}
