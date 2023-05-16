<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user_id = $request->get('user_id');

        if ($user_id) {
            return response([
                'data' => $this->repository->getUsersJobs($user_id)
            ]);
        } else if ($request->__authenticatedUser->user_type == config('admin.admin_role_id') ||
            $request->__authenticatedUser->user_type == config('admin.superadmin_role_id')) {
            return response([
                'data' => $response = $this->repository->getAll($request)
            ]);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return response([
            'data' => $this->repository->with('translatorJobRel.user')->find($id)
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();
        // Response is handled inside bookingRepository which isn't a good practice
        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response([
            'data' => $response
        ]);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        // Response is handled inside bookingRepository which isn't a good practice
        return response([
            'data' => $this->repository->updateJob($id, array_except($request->all(), ['_token', 'submit']), $request->__authenticatedUser)
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        // Response is handled inside bookingRepository which isn't a good practice
        return response([
            'data' => $this->repository->storeJobEmail($request->all())
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');

        if (! $user_id) {
            return response('Unauthorized', 401);
        }

        return response([
            'data' => $this->repository->getUsersJobsHistory($user_id, $request)
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        return response([
            'data' => $this->repository->acceptJob($request->all(), $request->__authenticatedUser)
        ]);
    }

    public function acceptJobWithId(Request $request)
    {
        return response([
            'data' => $this->repository->acceptJobWithId($request->get('job_id'), $request->__authenticatedUser)
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        return response([
            'data' => $this->repository->cancelJobAjax($request->all(), $request->__authenticatedUser)
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        return response([
            'data' => $this->repository->endJob($request->all())
        ]);
    }

    public function customerNotCall(Request $request)
    {
        return response([
            'data' => $this->repository->customerNotCall($request->all())
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        return response([
            'data' => $this->repository->getPotentialJobs($request->__authenticatedUser)
        ]);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = (isset($data['distance']) && $data['distance'] != "") ? $data['distance'] : "";
        $time = (isset($data['time']) && $data['time'] != "") ? $data['time'] : "";
        $job_id = (isset($data['jobid']) && $data['jobid'] != "") ? $data['jobid'] : "";
        $session = (isset($data['session_time']) && $data['session_time'] != "") ? $data['session_time'] : "";
        $flagged = ($data['flagged'] == "true") ? "yes" : "no";
        $admincomment = (isset($data['admincomment']) && $data['admincomment'] != "") ? $data['admincomment'] : "";

        if ($flagged === "yes" && $admincomment == "") {
            return response([
                "error" => ["comment" => "Please, add comment"]
            ], 422);
        }

        $manually_handled = ($data['manually_handled'] == "true") ? "yes" : "no";
        $by_admin = ($data['by_admin'] == "true") ? "yes" : "no";

        $colsUpdate = [];

        if ($time || $distance) {
            $colsUpdate = array_merge([
                'distance' => $distance,
                'time' => $time
            ], $colsUpdate);
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            $colsUpdate = array_merge([
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin,
            ], $colsUpdate);
        }

        if (empty($colsUpdate)) {
            return response([
                'error' => 'Nothing updated'
            ], 422);
        }

        $job = Distance::where('job_id', '=', $job_id)->first();

        $job->update($colsUpdate);

        return response([
            'message' => 'Record updated!'
        ]);
    }

    public function reopen(Request $request)
    {
        return response([
            'data' => $this->repository->reopen($request->all())
        ]);
    }

    public function resendNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid'));
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(
            ['success' => 'Push sent']
        );
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid'));
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['fail' => $e->getMessage()]);
        }
    }

}