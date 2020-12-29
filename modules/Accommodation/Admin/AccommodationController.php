<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 7/30/2019
 * Time: 1:56 PM
 */
namespace Modules\Accommodation\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AdminController;
use Modules\Core\Models\Attributes;
use Modules\Location\Models\Location;
use Modules\Accommodation\Models\Accommodation;
use Modules\Accommodation\Models\AccommodationTerm;
use Modules\Accommodation\Models\AccommodationTranslation;

class AccommodationController extends AdminController
{
    protected $accommodation;
    protected $accommodation_translation;
    protected $accommodation_term;
    protected $attributes;
    protected $location;
    public function __construct()
    {
        parent::__construct();
        $this->setActiveMenu('admin/module/accommodation');
        $this->accommodation = Accommodation::class;
        $this->accommodation_translation = AccommodationTranslation::class;
        $this->accommodation_term = AccommodationTerm::class;
        $this->attributes = Attributes::class;
        $this->location = Location::class;
    }

    public function callAction($method, $parameters)
    {
        if(!Accommodation::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }

    public function index(Request $request)
    {
        $this->checkPermission('accommodation_view');
        $query = $this->accommodation::query() ;
        $query->orderBy('id', 'desc');
        if (!empty($accommodation_name = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $accommodation_name . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('accommodation_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'accommodation_manage_others' => $this->hasPermission('accommodation_manage_others'),
            'breadcrumbs'        => [
                [
                    'name' => __('Accommodations'),
                    'url'  => 'admin/module/accommodation'
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Accommodation Management")
        ];
        return view('Accommodation::admin.index', $data);
    }

    public function recovery(Request $request)
    {
        $this->checkPermission('accommodation_view');
        $query = $this->accommodation::onlyTrashed() ;
        $query->orderBy('id', 'desc');
        if (!empty($accommodation_name = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $accommodation_name . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('accommodation_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'accommodation_manage_others' => $this->hasPermission('accommodation_manage_others'),
            'recovery'           => 1,
            'breadcrumbs'        => [
                [
                    'name' => __('Accommodations'),
                    'url'  => 'admin/module/accommodation'
                ],
                [
                    'name'  => __('Recovery'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Recovery Accommodation Management")
        ];
        return view('Accommodation::admin.index', $data);
    }

    public function create(Request $request)
    {
        $this->checkPermission('accommodation_create');
        $row = new $this->accommodation();
        $row->fill([
            'status' => 'publish'
        ]);
        $data = [
            'row'            => $row,
            'attributes'     => $this->attributes::where('service', 'accommodation')->get(),
            'accommodation_location' => $this->location::where('status', 'publish')->get()->toTree(),
            'translation'    => new $this->accommodation_translation(),
            'breadcrumbs'    => [
                [
                    'name' => __('Accommodations'),
                    'url'  => 'admin/module/accommodation'
                ],
                [
                    'name'  => __('Add Accommodation'),
                    'class' => 'active'
                ],
            ],
            'page_title'     => __("Add new Accommodation")
        ];
        return view('Accommodation::admin.detail', $data);
    }

    public function edit(Request $request, $id)
    {
        $this->checkPermission('accommodation_update');
        $row = $this->accommodation::find($id);
        if (empty($row)) {
            return redirect(route('accommodation.admin.index'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        if (!$this->hasPermission('accommodation_manage_others')) {
            if ($row->create_user != Auth::id()) {
                return redirect(route('accommodation.admin.index'));
            }
        }
        $data = [
            'row'            => $row,
            'translation'    => $translation,
            "selected_terms" => $row->terms->pluck('term_id'),
            'attributes'     => $this->attributes::where('service', 'accommodation')->get(),
            'accommodation_location'  => $this->location::where('status', 'publish')->get()->toTree(),
            'enable_multi_lang'=>true,
            'breadcrumbs'    => [
                [
                    'name' => __('Accommodations'),
                    'url'  => 'admin/module/accommodation'
                ],
                [
                    'name'  => __('Edit Accommodation'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Edit: :name",['name'=>$row->title])
        ];
        return view('Accommodation::admin.detail', $data);
    }

    public function store( Request $request, $id ){

        if($id>0){
            $this->checkPermission('accommodation_update');
            $row = $this->accommodation::find($id);
            if (empty($row)) {
                return redirect(route('accommodation.admin.index'));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('accommodation_manage_others'))
            {
                return redirect(route('accommodation.admin.index'));
            }
        }else{
            $this->checkPermission('accommodation_create');
            $row = new $this->accommodation();
            $row->status = "publish";
        }
        $dataKeys = [
            'title',
            'content',
            'price',
            'is_instant',
            'status',
            'video',
            'faqs',
            'image_id',
            'banner_image_id',
            'gallery',
            'bed',
            'bathroom',
            'square',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',
            'price',
            'sale_price',
            'max_guests',
            'enable_extra_price',
            'extra_price',
            'is_featured',
            'default_state',
            'min_day_before_booking',
            'min_day_stays',
        ];
        if($this->hasPermission('accommodation_manage_others')){
            $dataKeys[] = 'create_user';
        }

        $row->fillByAttr($dataKeys,$request->input());
        if($request->input('slug')){
            $row->slug = $request->input('slug');
        }
	    $row->ical_import_url  = $request->ical_import_url;

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);

        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }

            if($id > 0 ){
                return back()->with('success',  __('Accommodation updated') );
            }else{
                return redirect(route('accommodation.admin.edit',$row->id))->with('success', __('Accommodation created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        $this->checkPermission('accommodation_manage_attributes');
        if (empty($request->input('terms'))) {
            $this->accommodation_term::where('target_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->accommodation_term::firstOrCreate([
                    'term_id' => $term_id,
                    'target_id' => $row->id
                ]);
            }
            $this->accommodation_term::where('target_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function bulkEdit(Request $request)
    {

        $ids = $request->input('ids');
        $action = $request->input('action');
        if (empty($ids) or !is_array($ids)) {
            return redirect()->back()->with('error', __('No items selected!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }

        switch ($action){
            case "delete":
                foreach ($ids as $id) {
                    $query = $this->accommodation::where("id", $id);
                    if (!$this->hasPermission('accommodation_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('accommodation_delete');
                    }
                    $query->first();
                    if(!empty($query)){
                        $query->delete();
                    }
                }
                return redirect()->back()->with('success', __('Deleted success!'));
                break;
            case "recovery":
                foreach ($ids as $id) {
                    $query = $this->accommodation::where("id", $id);
                    if (!$this->hasPermission('accommodation_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('accommodation_delete');
                    }
                    $query->first();
                    if(!empty($query)){
                        $query->restore();
                    }
                }
                return redirect()->back()->with('success', __('Recovery success!'));
                break;
            case "clone":
                $this->checkPermission('accommodation_create');
                foreach ($ids as $id) {
                    (new $this->accommodation())->saveCloneByID($id);
                }
                return redirect()->back()->with('success', __('Clone success!'));
                break;
            default:
                // Change status
                foreach ($ids as $id) {
                    $query = $this->accommodation::where("id", $id);
                    if (!$this->hasPermission('accommodation_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('accommodation_update');
                    }
                    $query->update(['status' => $action]);
                }
                return redirect()->back()->with('success', __('Update success!'));
                break;
        }


    }
}
