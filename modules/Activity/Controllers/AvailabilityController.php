<?php
namespace Modules\Activity\Controllers;

use ICal\ICal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Booking;
use Modules\FrontendController;
use Modules\Activity\Models\Activity;
use Modules\Activity\Models\ActivityDate;

class AvailabilityController extends FrontendController
{
    protected $activityClass;
    /**
     * @var ActivityDate
     */
    protected $activityDateClass;
    /**
     * @var Booking
     */
    protected $bookingClass;
    protected $indexView = 'Activity::frontend.user.availability';

    public function __construct()
    {
        parent::__construct();
        $this->activityClass = Activity::class;
        $this->activityDateClass = ActivityDate::class;
        $this->bookingClass = Booking::class;
    }

    public function callAction($method, $parameters)
    {
        if (setting_item('activity_disable')) {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }

    public function index(Request $request)
    {
        $this->checkPermission('activity_create');
        $q = $this->activityClass::query();
        if ($request->query('s')) {
            $q->where('title', 'like', '%' . $request->query('s') . '%');
        }
        if (!$this->hasPermission('activity_manage_others')) {
            $q->where('create_user', $this->currentUser()->id);
        }
        $q->orderBy('bravo_activities.id', 'desc');
        $rows = $q->paginate(15);
        $current_month = strtotime(date('Y-m-01', time()));
        if ($request->query('month')) {
            $date = date_create_from_format('m-Y', $request->query('month'));
            if (!$date) {
                $current_month = time();
            } else {
                $current_month = $date->getTimestamp();
            }
        }
        $breadcrumbs = [
            [
                'name' => __('Activities'),
                'url'  => route('activity.vendor.index')
            ],
            [
                'name'  => __('Availability'),
                'class' => 'active'
            ],
        ];
        $page_title = __('Activities Availability');
        return view($this->indexView, compact('rows', 'breadcrumbs', 'current_month', 'page_title', 'request'));
    }

    public function loadDates(Request $request)
    {

        $rules = [
            'id'    => 'required',
            'start' => 'required',
            'end'   => 'required',
        ];
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }
        $activity = $this->activityClass::find($request->query('id'));
        if (empty($activity)) {
            return $this->sendError(__('Activity not found'));
        }
        $is_single = $request->query('for_single');
        $query = $this->activityDateClass::query();
        $query->where('target_id', $request->query('id'));
        $query->where('start_date', '>=', date('Y-m-d H:i:s', strtotime($request->query('start'))));
        $query->where('end_date', '<=', date('Y-m-d H:i:s', strtotime($request->query('end'))));
        $rows = $query->take(50)->get();
        $allDates = [];
//        for ($i = strtotime($request->query('start')); $i <= strtotime($request->query('end')); $i += DAY_IN_SECONDS) {
        $period = periodDate($request->input('start'),$request->input('end'));
        foreach ($period as $dt){
            $i = $dt->getTimestamp();
            $date = [
                'id'         => rand(0, 999),
                'active'     => 0,
                'price'      => (!empty($activity->sale_price) and $activity->sale_price > 0 and $activity->sale_price < $activity->price) ? $activity->sale_price : $activity->price,
                'is_default' => true,
                'textColor'  => '#2791fe'
            ];
            if (!$is_single) {
                $date['price_html'] = format_money_main($date['price']);
            } else {
                $date['price_html'] = format_money($date['price']);
            }
            $date['max_guests'] = $activity->max_people;
            $date['title'] = $date['event'] = $date['price_html'];
            $date['start'] = $date['end'] = date('Y-m-d', $i);
            if ($activity->default_state) {
                $date['active'] = 1;
            } else {
                $date['title'] = $date['event'] = __('Blocked');
                $date['backgroundColor'] = 'orange';
                $date['borderColor'] = '#fe2727';
                $date['classNames'] = ['blocked-event'];
                $date['textColor'] = '#fe2727';
            }
            if ($request->input('for_single')) {
                if (empty(!$activity->max_people) and $activity->max_people < 1) {
                    $date['active'] = 0;
                }
            }
            if (!empty($activity->meta->enable_person_types) and $activity->meta->enable_person_types == 1) {
                $date['person_types'] = $activity->meta->person_types;
                if (!empty($date['person_types'])) {
                    $c_title = "";
                    foreach ($date['person_types'] as &$person) {

                        if (!$is_single) {
                            $c_title .= $person['name'] . ": " . format_money_main($person['price']) . "<br>";
                            //for single
                            $person['display_price'] = format_money_main($person['price']);
                        } else {
                            $c_title .= $person['name'] . ": " . format_money($person['price']) . "<br>";
                            //for single
                            $person['display_price'] = format_money($person['price']);
                        }
                        $person['number'] = $person['min'] ?? 0;
                    }
                    $date['title'] = $date['event'] = $c_title;
                }
            }
            // Open Hours
            if (!empty($activity->meta->enable_open_hours) and $activity->meta->enable_open_hours == 1) {
                $open_hours = $activity->meta->open_hours;
                $nDate = date('N', $i);
                if (!isset($open_hours[$nDate]) or empty($open_hours[$nDate]['enable'])) {
                    $date['active'] = 0;
                }
            }
            $allDates[date('Y-m-d', $i)] = $date;
        }
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $row->start = date('Y-m-d', strtotime($row->start_date));
                $row->end = date('Y-m-d', strtotime($row->start_date));
                $row->textColor = '#2791fe';
                $price = $row->price;
                if (empty($price)) {
                    $price = (!empty($activity->sale_price) and $activity->sale_price > 0 and $activity->sale_price < $activity->price) ? $activity->sale_price : $activity->price;
                }
                if (!$is_single) {
                    $row->title = $row->event = format_money_main($price);
                } else {
                    $row->title = $row->event = format_money($price);
                }
                $row->price = $price;
                if ($request->input('for_single')) {
                    if (empty(!$row->max_guests) and $row->max_guests < 1) {
                        $row->active = 0;
                    }
                }
                $list_person_types = null;
                if (!empty($activity->meta->enable_person_types) and $activity->meta->enable_person_types == 1) {
                    $list_person_types = $activity->meta->person_types;
                    $date_person_types = is_array($row->person_types) ? $row->person_types : [];
                    if (!empty($list_person_types) and is_array($list_person_types)) {
                        $c_title = "";
                        foreach ($list_person_types as $k => &$person) {
                            $person['price'] = $date_person_types[$k]['price'] ?? $person['price'];
                            $person['max'] = $date_person_types[$k]['max'] ?? $person['max'];
                            $person['min'] = $date_person_types[$k]['min'] ?? $person['min'];
                            if (!$is_single) {
                                $c_title .= $person['name'] . ": " . format_money_main($person['price']) . "<br>";
                                //for single
                                $person['display_price'] = format_money_main($person['price']);
                            } else {
                                $c_title .= $person['name'] . ": " . format_money($person['price']) . "<br>";
                                //for single
                                $person['display_price'] = format_money($person['price']);
                            }
                            $person['number'] = $person['min'] ?? 0;
                        }
                        $row->title = $c_title;
                    }
                }
                $row->person_types = $list_person_types;
                if (!$row->active) {
                    $row->title = $row->event = __('Blocked');
                    $row->backgroundColor = '#fe2727';
                    $row->classNames = ['blocked-event'];
                    $row->textColor = '#fe2727';
                    $row->active = 0;
                } else {
                    $row->classNames = ['active-event'];
                    $row->active = 1;
                    // Open Hours
                    if (!empty($activity->meta->enable_open_hours) and $activity->meta->enable_open_hours == 1) {
                        $open_hours = $activity->meta->open_hours;
                        $nDate = date('N', strtotime($row->start_date));
                        if (!isset($open_hours[$nDate]) or empty($open_hours[$nDate]['enable'])) {
                            $row->active = 0;
                        }
                    }
                }
                $allDates[date('Y-m-d', strtotime($row->start_date))] = $row->toArray();
            }
        }
        $bookings = $this->bookingClass::getBookingInRanges($activity->id, $activity->type, $request->query('start'), $request->query('end'));
        if (!empty($bookings)) {
            foreach ($bookings as $booking) {
//                for ($i = strtotime($booking->start_date); $i <= strtotime($booking->end_date); $i += DAY_IN_SECONDS) {
                $period = periodDate($booking->start_date,$booking->end_date);
                foreach ($period as $dt){
                    $i = $dt->getTimestamp();
                    if (isset($allDates[date('Y-m-d', $i)])) {
                        $total_guests_booking = $booking->total_guests;
                        $max_guests = $allDates[date('Y-m-d', $i)]['max_guests'];
                        if ($total_guests_booking >= $max_guests) {
                            $allDates[date('Y-m-d', $i)]['active'] = 0;
                            $allDates[date('Y-m-d', $i)]['event'] = __('Full Book');
                            $allDates[date('Y-m-d', $i)]['title'] = __('Full Book');
                            $allDates[date('Y-m-d', $i)]['classNames'] = ['full-book-event'];
                        }
                    }
                }
            }
        }
        if (!empty($activity->ical_import_url)) {
            $startDate = $request->query('start');
            $endDate = $request->query('end');
            $timezone = setting_item('site_timezone', config('app.timezone'));
            try {
                $icalevents = new Ical($activity->ical_import_url, [
                    'defaultTimeZone' => $timezone
                ]);
                $eventRange = $icalevents->eventsFromRange($startDate, $endDate);
                if (!empty($eventRange)) {
                    foreach ($eventRange as $item => $value) {
                        if (!empty($date = $value->dtstart_array[2])) {
                            $max_guests = $allDates[date('Y-m-d', $date)]['max_guests'] - 1;
                            $allDates[date('Y-m-d', $date)]['max_guests'] = $max_guests;
                            if ($max_guests == 0) {
                                $allDates[date('Y-m-d', $date)]['active'] = 0;
                                $allDates[date('Y-m-d', $date)]['event'] = __('Full Book');
                                $allDates[date('Y-m-d', $date)]['title'] = __('Full Book');
                                $allDates[date('Y-m-d', $date)]['classNames'] = ['full-book-event'];
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                return $this->sendError($exception->getMessage());
            }
        }
        $data = array_values($allDates);
        return response()->json($data);
    }

    public function store(Request $request)
    {

        $request->validate([
            'target_id'  => 'required',
            'start_date' => 'required',
            'end_date'   => 'required'
        ]);
        $activity = $this->activityClass::find($request->input('target_id'));
        $target_id = $request->input('target_id');
        if (empty($activity)) {
            return $this->sendError(__('Activity not found'));
        }
        if (!$this->hasPermission('activity_manage_others')) {
            if ($activity->create_user != Auth::id()) {
                return $this->sendError("You do not have permission to access it");
            }
        }
        $postData = $request->input();
        if (!empty($person_types = $postData['person_types']) and is_array($person_types)) {
            foreach ($person_types as &$item) {
                $item['display_price'] = format_money($item['price']);
            }
            $postData['person_types'] = $person_types;
        } else {
            $postData['person_types'] = null;
        }
//        for ($i = strtotime($request->input('start_date')); $i <= strtotime($request->input('end_date')); $i += DAY_IN_SECONDS) {
        $period = periodDate($request->input('start'),$request->input('end'));
        foreach ($period as $dt){
            $date = $this->activityDateClass::where('start_date', $dt->format('Y-m-d'))->where('target_id', $target_id)->first();
            if (empty($date)) {
                $date = new $this->activityDateClass();
                $date->target_id = $target_id;
            }
            $postData['start_date'] = $dt->format('Y-m-d H:i:s');
            $postData['end_date'] = $dt->format('Y-m-d H:i:s');
            $date->fillByAttr([
                'start_date',
                'end_date',
                'price',
                'max_guests',
                'active',
                'person_types'
            ], $postData);
            $date->save();
        }
        return $this->sendSuccess([], __("Update Success"));
    }
}
