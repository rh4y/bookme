<?php
namespace Modules\Boat\Blocks;

use Modules\Template\Blocks\BaseBlock;
use Modules\Location\Models\Location;
use Modules\Media\Helpers\FileHelper;

class FormSearchBoat extends BaseBlock
{
    function __construct()
    {
        $this->setOptions([
            'settings' => [
                [
                    'id'        => 'title',
                    'type'      => 'input',
                    'inputType' => 'text',
                    'label'     => __('Title')
                ],
                [
                    'id'        => 'sub_title',
                    'type'      => 'input',
                    'inputType' => 'text',
                    'label'     => __('Sub Title')
                ],
                [
                    'id'    => 'bg_image',
                    'type'  => 'uploader',
                    'label' => __('Background Image Uploader')
                ],
            ]
        ]);
    }

    public function getName()
    {
        return __('Boat: Form Search');
    }

    public function content($model = [])
    {
        $limit_location = 15;
        if( empty(setting_item("boat_location_search_style")) or setting_item("boat_location_search_style") == "normal" ){
            $limit_location = 1000;
        }
        $data = [
            'list_location' => Location::where("status","publish")->limit($limit_location)->with(['translations'])->get()->toTree(),
            'bg_image_url'  => '',
        ];
        $data = array_merge($model, $data);
        if (!empty($model['bg_image'])) {
            $data['bg_image_url'] = FileHelper::url($model['bg_image'], 'full');
        }
        return view('Boat::frontend.blocks.form-search-boat.index', $data);
    }

    public function contentAPI($model = []){
        if (!empty($model['bg_image'])) {
            $model['bg_image_url'] = FileHelper::url($model['bg_image'], 'full');
        }
        return $model;
    }
}
