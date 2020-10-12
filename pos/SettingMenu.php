<?php

namespace App\Http\Controllers\API\v1;

use App\Facades\ReturnData;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Validator;
use DB;
use App\Events\CallEvent;
use App\Model\Store;
use App\Model\StoreMenu;
use App\Model\StoreMenuItem;
use App\Model\StoreMenuItemOption;
use App\Model\StoreMenuItemOptionItem;
use Log;

class SettingMenu extends Controller
{
  public function __invoke(Request $request, $id)
  {
    $data = $request->all();
    $method = $request->method();
    $work = ['GET'=>'MenuList', 'POST'=>'MenuUpdate', 'PUT'=>'MenuStore', 'DELETE'=>'MenuDelete'];

    $class = '\\'.__NAMESPACE__.'\\'.$work[$method];

    $result = new $class($id, $data);
    if ($result->getResponse() == 200) {
      return ReturnData::setData($result->getResult())->json_data($result->getResponse());
    }
    else {
      return ReturnData::setError($result->getError())->json_data($result->getResponse());
    }
  }

}

interface MenuBaseInterface
{
  public function menu();
  public function menu_item();
  public function menu_item_option();
  public function menu_item_option_item();
}

class MenuBase
{
  protected $data;
  protected $store;
  protected $user_id;
  protected $result;
  protected $response;
  protected $error;

  public function __construct($type, $data)
  {
    $this->result = $this->error = '';
    $this->response = 200;

    $this->data = $data;
    $this->user_id = auth()->user()->id ?? '1';
    $this->store = Store::find($this->data['store_id']);

    //쿠키랑 비교할 무언가가 필요하다
    // if ($this->data['store_id'] != $this->store->id) {
    //   $this->response = 400;
    //   $this->error = '너는 누구지?';
    //   return;
    // }

    try {
      $this->{$type}();
    } catch (\Throwable $th) {
      $this->result = $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function getResult()
  {
    return $this->result;
  }

  public function getResponse()
  {
    return $this->response;
  }

  public function getError()
  {
    return $this->error;
  }
}

class MenuStore extends MenuBase implements MenuBaseInterface
{
  public function menu()
  {
    try {
      DB::beginTransaction();

      $store_menu = StoreMenu::updateOrCreate(['store_id'=>$this->store->id, 'name'=>$this->data['category']], ['updated_at'=>now(), 'deleted_at'=>NULL]);
      $this->result = ['lastInsertId'=> $store_menu->id];

      DB::commit();
    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }

  }

  public function menu_item()
  {
    $data = $this->data;
    try {
      DB::beginTransaction();

      $count = DB::table('store_menu_item')
        ->where('store_id', $data['store_id'])
        ->where('name', $data['menu_name'])
        ->whereNull('deleted_at')
        ->count();

      if ($count > 0) {
        $this->error = ['modal_menu_name'=>'이미 등록된 메뉴입니다.'];
        $this->response = 422;
      }
      else {
        $menu_item = DB::table('store_menu_item')->insertGetId([
          'store_id' => $data['store_id'],
          'store_menu_id' => $data['category_id'],
          'name' => $data['menu_name'],
          'price' => $data['menu_price'],
        ]);
        $this->result = $menu_item;

        DB::commit();
      }
    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function menu_item_option()
  {
    $data = $this->data;
    $options = $data['option_menu_item_option'];

    try{
      DB::beginTransaction();

      foreach ($options as $key => $value) {
        $option = $value['option_category_type'] === '다중 옵션' ? 'checkbox' : 'radio';
  
        $store_menu_item_option_id = DB::table('store_menu_item_option')->insertGetId(
          [
            'name'=>$value['option_catergory_name'],
            'type'=>$option,
            'store_id'=>$data['store_id'],
            'store_menu_item_id'=>$data['store_menu_item_id']
          ]);
  
        foreach ($value['option_menu_item'] as $k => $v) {
          DB::table('store_menu_item_option_item')->insert(
            [
              'store_menu_item_option_id'=>$store_menu_item_option_id,
              'store_id'=>$data['store_id'],
              'name'=>$v['option_item_name'],
              'price'=>$v['option_item_price']
            ]
          );
        }
      }
      DB::commit();
    }  catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function menu_item_option_item()
  {

  }
}

class MenuUpdate extends MenuBase implements MenuBaseInterface
{
  public function menu()
  {
    try {
      DB::beginTransaction();

      $data = $this->data;
      foreach ($data as $key => $value) {
        if ($key === 'store_id') {
          continue;
        }
        StoreMenu::find($value['id'])->update(['name' => $value['name']]);
      }
      DB::commit();
    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function menu_item()
  {

  }

  public function menu_item_option()
  {

  }

  public function menu_item_option_item()
  {
    $data = $this->data;
    try {
      DB::beginTransaction();

      if ($data['basic_changes'][0] == 'true') { //change category
        $count = StoreMenuItem::where('store_menu_id', $data['category_id'])->where('name',$data['menu_item_name'])->count();
        if ($count > 0) { //바뀐 카테고리에 똑같은 메뉴가 있다
          $this->error = '변경될 카테고리 안에 똑같은 이름의 메뉴가 있습니다';
          $this->response = 400;
          return;
        } else {
          //기존 데이터 delete
          $delete = new MenuDelete('menu_item', $data);

          //data insert
          $store_menu_item = new MenuStore('menu_item', ['store_id'=>$data['store_id'], 'menu_name'=>$data['menu_item_name'], 'category_id'=>$data['category_id'], 'menu_price'=>$data['main_menu_price']  ]);

          $data = array_merge($data, [
            'store_menu_item_id' => $store_menu_item->result
          ]);

          $store_menu_item_option = new MenuStore('menu_item_option', $data);
        }
      }
      else { //카테고리가 안바꼈어.
        if ($data['basic_changes'][1] == 'true') { //메뉴가 바꼈어.
          $count = StoreMenuItem::find($data['menu_item'])->where('name',$data['menu_item_name'])->whereNull('deleted_at')->count();
          if ($count > 0) {
            $this->error = '똑같은 이름의 메뉴가 있습니다';
            $this->response = 400;
            return;
          }

          $delete = new MenuDelete('menu_item', $data);

          //data insert
          $store_menu_item = new MenuStore('menu_item', ['store_id'=>$data['store_id'], 'menu_name'=>$data['menu_item_name'], 'category_id'=>$data['category_id'], 'menu_price'=>$data['main_menu_price']  ]);

          $data = array_merge($data, [
            'store_menu_item_id' => $store_menu_item->result
          ]);

          $store_menu_item_option = new MenuStore('menu_item_option', $data);

        }
        else { // 카테고리, 메뉴가 안바꼈어.
          $delete = new MenuDelete('menu_item_option', $data);

          StoreMenuItem::find($data['menu_item'])->update([
            'price'=>$data['main_menu_price']
          ]);

          $menuItemOption = StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->select('id')->get()->toArray();

          $menuItemOptionID = array_column($menuItemOption, 'id');

          StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->delete();

          StoreMenuItemOptionItem::whereIn('store_menu_item_option_id', $menuItemOptionID)->delete();

          //updateOrInsert

          $options = $data['option_menu_item_option'];
          foreach ($options as $key => $value) {
            $option = $value['option_category_type'] === '다중 옵션' ? 'checkbox' : 'radio';

            $store_menu_item_option = StoreMenuItemOption::withTrashed()
              ->where('id', $value['html_option_category_id'])
              ->where('name', $value['option_catergory_name'])
              ->first();

            if (isset($store_menu_item_option) && $store_menu_item_option->count() > 0) { //안바뀐 옵션
              $store_menu_item_option->type = $option;
              $store_menu_item_option->deleted_at = NULL;
              $store_menu_item_option->save();

              foreach ($value['option_menu_item'] as $k => $v) {
                $option_item = new StoreMenuItemOptionItem;
                $option_item->store_menu_item_option_id = $store_menu_item_option->id;
                $option_item->name = $v['option_item_name'];
                $option_item->price = $v['option_item_price'];
                $option_item->deleted_at = NULL;
                $option_item->store_id = $data['store_id'];
                $option_item->save();
              }

            }
            else {
              $store_menu_item_option = new StoreMenuItemOption;
              $store_menu_item_option->type = $option;
              $store_menu_item_option->name = $value['option_catergory_name'];
              $store_menu_item_option->store_id = $data['store_id'];
              $store_menu_item_option->store_menu_item_id = $data['menu_item'];
              $store_menu_item_option->save();

              foreach ($value['option_menu_item'] as $k => $v) {
                $option_item = new StoreMenuItemOptionItem;
                $option_item->store_menu_item_option_id = $store_menu_item_option->id;
                $option_item->name = $v['option_item_name'];
                $option_item->price = $v['option_item_price'];
                $option_item->deleted_at = NULL;
                $option_item->store_id = $data['store_id'];
                $option_item->save();
              }
            }
          }
        }

      }
      DB::commit();

    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

}

class MenuDelete extends MenuBase implements MenuBaseInterface
{
  public function menu()
  {
    try {
      DB::beginTransaction();

      $data = $this->data;
      $id = $data['id'];
      $this->data = array_merge($this->data, [
        'menu_item' => $id
      ]);

      StoreMenu::find($data['id'])->delete();

      $store_menu_item = StoreMenuItem::where('store_menu_id', $data['id'])->get();

      if ( isset($store_menu_item) ) {
        foreach ($store_menu_item as $key => $value) {
          $this->data['menu_item'] = $value->id;
          $this->menu_item();
        }
      }
      DB::commit();
    }  catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function menu_item()
  {
    try {
      DB::beginTransaction();

      $data = $this->data;
      $result = StoreMenuItem::find($data['menu_item'])->delete();
      if ($result == true) {
        $StoreMenuItemOptionID = StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->pluck('id');

        if ($StoreMenuItemOptionID->count() > 0 ) {
          StoreMenuItemOptionItem::whereIn('store_menu_item_option_id', $StoreMenuItemOptionID)->delete();

          StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->delete();
        }
        DB::commit();
      }
      else {
        // 소프트 딜리트 실패
        Log::info('SettingMenu.php 236');
      }

    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }

  public function menu_item_option()
  {
    try {
      DB::beginTransaction();

      $data = $this->data;
      

      $StoreMenuItemOptionID = StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->pluck('id');
      if ($StoreMenuItemOptionID->count() > 0 ) {
        StoreMenuItemOptionItem::whereIn('store_menu_item_option_id', $StoreMenuItemOptionID)->delete();

        StoreMenuItemOption::where('store_menu_item_id', $data['menu_item'])->delete();
      }
      DB::commit();
    } catch (\Throwable $th) {
      DB::rollback();
      $this->error = $th->getMessage();
      $this->response = 500;
    }
  }
  public function menu_item_option_item()
  {

  }
}

class MenuList extends MenuBase implements MenuBaseInterface
{
  public function menu()
  {
    try {
      $data = StoreMenu::list($this->store->id);
      $this->result = $data;

    } catch (\Throwable $th) {
      $this->error = $th->getMessage();
      $this->response = 400;
    }

  }

  public function menu_item()
  {
      $category_id = $this->data['category_id'];
      // $data = StoreMenu::where('store_id', $this->store->id)->where('id', $category_id)->with('menuItem')->get();
      $store_menu = StoreMenu::find($category_id);

      $menuItem = $store_menu->menuItem->toArray();

      $menuItemOption = $store_menu->menuItemOption->where('store_id', $this->store->id)->toArray();

      $item_id = array_column($menuItemOption, 'id');

      $menuItemOptionItem = StoreMenuItemOptionItem::whereIn('store_menu_item_option_id', $item_id)->get();

      $data = [];
      $data2 = [];

      foreach ($menuItem as $key => $value) {
        $data[$value['id'] ] = $value;
      }

      foreach ($menuItemOption as $key => $value) {
        $data2[$value['id'] ] = $value;
      }

      foreach ($menuItemOptionItem as $key => $value) {
        $data2[$value['store_menu_item_option_id'] ]['option_item'][] = $value;
      }

      foreach ($data2 as $key => $value) {
        if (isset($value['option_item']))
          $data[$value['store_menu_item_id'] ]['item'][] = $value;
      }

      $menuItemCount = StoreMenuItem::myCount($this->store->id);

      $data['count'] = $menuItemCount;

      $this->result = $data;
  }

  public function menu_item_option()
  {

  }
  public function menu_item_option_item()
  {

  }
}

