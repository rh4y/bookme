<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 7/30/2019
 * Time: 1:56 PM
 */
namespace Modules\Sauna\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AdminController;
use Modules\Sauna\Models\Sauna;
use Modules\Sauna\Models\SaunaTerm;
use Modules\Sauna\Models\SaunaTranslation;
use Modules\Core\Models\Attributes;
use Modules\Location\Models\Location;

class SaunaController extends AdminController
{
    protected $sauna;
    protected $sauna_translation;
    protected $sauna_term;
    protected $attributes;
    protected $location;
    public function __construct()
    {
        parent::__construct();
        $this->setActiveMenu(route('sauna.admin.index'));
        $this->sauna = Sauna::class;
        $this->sauna_translation = SaunaTranslation::class;
        $this->sauna_term = SaunaTerm::class;
        $this->attributes = Attributes::class;
        $this->location = Location::class;
    }

    public function callAction($method, $parameters)
    {
        if(!Sauna::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function index(Request $request)
    {
        $this->checkPermission('sauna_view');
        $query = $this->sauna::query() ;
        $query->orderBy('id', 'desc');
        if (!empty($s = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $s . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('sauna_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'sauna_manage_others' => $this->hasPermission('sauna_manage_others'),
            'breadcrumbs'        => [
                [
                    'name' => __('Saunas'),
                    'url'  => 'admin/module/sauna'
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Sauna Management")
        ];
        return view('Sauna::admin.index', $data);
    }

    public function recovery(Request $request)
    {
        $this->checkPermission('sauna_view');
        $query = $this->sauna::onlyTrashed() ;
        $query->orderBy('id', 'desc');
        if (!empty($s = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $s . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('sauna_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'sauna_manage_others' => $this->hasPermission('sauna_manage_others'),
            'recovery'           => 1,
            'breadcrumbs'        => [
                [
                    'name' => __('Saunas'),
                    'url'  => 'admin/module/sauna'
                ],
                [
                    'name'  => __('Recovery'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Recovery Sauna Management")
        ];
        return view('Sauna::admin.index', $data);
    }

    public function create(Request $request)
    {
        $this->checkPermission('sauna_create');
        $row = new $this->sauna();
        $row->fill([
            'status' => 'publish'
        ]);
        $data = [
            'row'            => $row,
            'attributes'     => $this->attributes::where('service', 'sauna')->get(),
            'sauna_location' => $this->location::where('status', 'publish')->get()->toTree(),
            'translation'    => new $this->sauna_translation(),
            'breadcrumbs'    => [
                [
                    'name' => __('Saunas'),
                    'url'  => route('sauna.admin.index')
                ],
                [
                    'name'  => __('Add Sauna'),
                    'class' => 'active'
                ],
            ],
            'page_title'     => __("Add new Sauna")
        ];
        return view('Sauna::admin.detail', $data);
    }

    public function edit(Request $request, $id)
    {
        $this->checkPermission('sauna_update');
        $row = $this->sauna::find($id);
        if (empty($row)) {
            return redirect(route('sauna.admin.index'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        if (!$this->hasPermission('sauna_manage_others')) {
            if ($row->create_user != Auth::id()) {
                return redirect(route('sauna.admin.index'));
            }
        }
        $data = [
            'row'            => $row,
            'translation'    => $translation,
            "selected_terms" => $row->terms->pluck('term_id'),
            'attributes'     => $this->attributes::where('service', 'sauna')->get(),
            'sauna_location'  => $this->location::where('status', 'publish')->get()->toTree(),
            'enable_multi_lang'=>true,
            'breadcrumbs'    => [
                [
                    'name' => __('Saunas'),
                    'url'  => route('sauna.admin.index')
                ],
                [
                    'name'  => __('Edit Sauna'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Edit: :name",['name'=>$row->title])
        ];
        return view('Sauna::admin.detail', $data);
    }

    public function store( Request $request, $id ){

        if($id>0){
            $this->checkPermission('sauna_update');
            $row = $this->sauna::find($id);
            if (empty($row)) {
                return redirect(route('sauna.admin.index'));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('sauna_manage_others'))
            {
                return redirect(route('sauna.admin.index'));
            }
        }else{
            $this->checkPermission('sauna_create');
            $row = new $this->sauna();
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
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',

            'duration',
            'start_time',
            'price',
            'sale_price',
            'ticket_types',

            'enable_extra_price',
            'extra_price',
            'is_featured',
            'default_state',
        ];
        if($this->hasPermission('sauna_manage_others')){
            $dataKeys[] = 'create_user';
        }

        $row->fillByAttr($dataKeys,$request->input());
        if($request->input('slug')){
            $row->slug = $request->input('slug');
        }

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);

        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }

            if($id > 0 ){
                return back()->with('success',  __('Sauna updated') );
            }else{
                return redirect(route('sauna.admin.edit',$row->id))->with('success', __('Sauna created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        $this->checkPermission('sauna_manage_attributes');
        if (empty($request->input('terms'))) {
            $this->sauna_term::where('target_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->sauna_term::firstOrCreate([
                    'term_id' => $term_id,
                    'target_id' => $row->id
                ]);
            }
            $this->sauna_term::where('target_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
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
                    $query = $this->sauna::where("id", $id);
                    if (!$this->hasPermission('sauna_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('sauna_delete');
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
                    $query = $this->sauna::where("id", $id);
                    if (!$this->hasPermission('sauna_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('sauna_delete');
                    }
                    $query->first();
                    if(!empty($query)){
                        $query->restore();
                    }
                }
                return redirect()->back()->with('success', __('Recovery success!'));
                break;
            case "clone":
                $this->checkPermission('sauna_create');
                foreach ($ids as $id) {
                    (new $this->sauna())->saveCloneByID($id);
                }
                return redirect()->back()->with('success', __('Clone success!'));
                break;
            default:
                // Change status
                foreach ($ids as $id) {
                    $query = $this->sauna::where("id", $id);
                    if (!$this->hasPermission('sauna_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('sauna_update');
                    }
                    $query->update(['status' => $action]);
                }
                return redirect()->back()->with('success', __('Update success!'));
                break;
        }


    }
}
