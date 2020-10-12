<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use GuzzleHttp\Client;
use Debugbar;
use Visitor;
use Helper;
use Storage;
use DB;
use Carbon\Carbon; //시간계산
use App\Product;
use App\ProductOption;
use App\User;

use AmazonProduct;
use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\BrowseNodeLookup;

class mainController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    
    public function __construct()
    {
        $this->middleware('auth');
        Visitor::log();
    }

    public function test() 
    {
        echo phpinfo();
        exit;
        $now = Carbon::now();
        echo $now->format('Y-m-d');
        exit;

        $handle = fopen("data/test.tab", "r");
        if ($handle) {
            $count = 0;
            $data = array();
            $tab = array('0', '0', '0', '0', '0', '0');
            $result='';
            while (($line = fgets($handle)) !== false) {
                $pid='';
                $tab_count = substr_count($line, "\t");
                
                $tab[$tab_count] = $count;

                if ($tab_count == 0) {
                    $data[$count]['text'] = $line;
                    $data[$count]['pid'] = 0;
                    $data[$count]['id'] = $count;
                }
                else {
                    $data[$count]['text'] = $line;
                    $data[$count]['pid'] = $tab[$tab_count-1];
                    $data[$count]['id'] = $count;
                }

                $result.= "{ id:".$data[$count]['id'].", pId:".$data[$count]['pid'].", name:'".$data[$count]['text']."'},";
                $count++;
                
            }
            echo $result;
            
            exit;

            fclose($handle);
        } else {
            // error opening the file.
        } 
        return view('test');
    }

    public function index()
    {
        $total_traffic = Visitor::clicks();
        $total_count = Visitor::count();
        
        $now = Carbon::now();
        $today_traffic = Visitor::range($now, $now);

        return view('dashboard')->with('cate', '0')
                                ->with('total_traffic', $total_traffic)
                                ->with('total_count', $total_count)
                                ->with('today_traffic', $today_traffic);
    }

    public function userDetail(Request $request)
    {
        return view('environment.userDetail')->with('cate', '4');
    }

    public function categorySetting(Request $request)
    {
        $lazada = Storage::disk('local')->get('xml/lazada/category.json');
        $handle = fopen("data/xml/amazon/category.tab", "r");

        $a2l_deny = DB::table('a2l_deny')->pluck('brand')
                                         ->toArray();
        $amazon='';
        
        if ($handle) {
            $count = 0;
            $data = array();
            $tab = array('0', '0', '0', '0', '0', '0');
            while (($line = fgets($handle)) !== false) {
                $pid='';
                $tab_count = substr_count($line, "\t");
                $line = str_replace("\n", "", $line);
                $line = str_replace("\r", "", $line);
                $line = str_replace("\t", "", $line);

                $tab[$tab_count] = $count;

                if ($tab_count == 0) {
                    $data[$count]['text'] = str_replace("'", "\\'", $line);
                    $data[$count]['pid'] = -1;
                    $data[$count]['id'] = $count;
                }
                else {
                    $data[$count]['text'] = str_replace("'", "\\'", $line);
                    $data[$count]['pid'] = $tab[$tab_count-1];
                    $data[$count]['id'] = $count;
                }

                $amazon.= "{ id:".$data[$count]['id'].", pId:".$data[$count]['pid'].", tab:".$tab_count.", name:'".$data[$count]['text']."'},";
                $count++;
            }

            $amazon = substr($amazon, 0, strlen($amazon) - 1);

            fclose($handle);
        } else {
            // error opening the file.
        } 

        return view('environment.category')->with('cate', '4')
                                           ->with('a2l_deny', htmlspecialchars_decode(json_encode($a2l_deny)))
                                           ->with('lazada', htmlspecialchars_decode($lazada))
                                           ->with('amazon', htmlspecialchars_decode($amazon));
    }



    public function searchAmazon(Request $request, $param='')
    {
        $param2='';
        $isProductDetailPage = '';

        $temp = Input::all();

        foreach ($temp as $key => $value) {
            $param2.=$key.'='.$value.'&';
        }

        if (!$param) {
            $param = 'gp/site-directory/ref=nav_shopall_fullstore';
        }
        
        Debugbar::info($param2);
        
        if (substr($param, 0, 10) == 'gp/product' || strpos($param, 'dp/') !== false || strpos($param2, 'dp/') !== false) $isProductDetailPage = true;
        
        return view('management.search.amazon')->with('cate', '1')
                                               ->with('isProductDetailPage', $isProductDetailPage)
                                               ->with('param', $param)
                                               ->with('param2', $param2);
    }
    
    public function goodsList(Request $request)
    {
        $goods = Product::where('user_id', '=', Auth::user()->id)
                        ->orderBy('id', 'desc')
                        ->paginate(10);

        return view('goods.list')->with('cate', '1')
                                 ->with('goods', $goods);
    }

    public function goodsView(Request $request, $product_id='0')
    {
        $product = Product::find($product_id);
        $productOption = ProductOption::where('user_id', Auth::user()->id)
                                      ->where('parent_id', $product_id)
                                      ->orderBy('id', 'desc')
                                      ->get();
                                      
                                                
        $amazon_cates = explode('|', $product->cate_text);

        $a2l_cate = DB::table('a2l_category')->where('amazon_cate_1', isset($amazon_cates[0]) ? $amazon_cates[0] : '')
                                        ->where('amazon_cate_2', isset($amazon_cates[1]) ? $amazon_cates[1] : '')
                                        ->where('amazon_cate_3', isset($amazon_cates[2]) ? $amazon_cates[2] : '')
                                        ->where('amazon_cate_4', isset($amazon_cates[3]) ? $amazon_cates[3] : '')
                                        ->where('amazon_cate_5', isset($amazon_cates[4]) ? $amazon_cates[4] : '')
                                        ->where('amazon_cate_7', isset($amazon_cates[6]) ? $amazon_cates[6] : '')
                                        ->where('amazon_cate_6', isset($amazon_cates[5]) ? $amazon_cates[5] : '')
                                        ->first();

        return view('goods.view')->with('cate', '1')
                                 ->with('product', $product)
                                 ->with('a2l_cate', $a2l_cate)
                                 ->with('amazon_cates', $amazon_cates)
                                 ->with('productOption', $productOption);
    }

    public function goodsUpdate(Request $request, $product_id='0')
    {
        $uid = Auth::user()->id;
        
        $data = $request->all();
        $product = Product::find($product_id);
        $productOptions = ProductOption::where('user_id', $uid)
                                      ->where('parent_id', $product_id)
                                      ->orderBy('id', 'desc')
                                      ->get();
        //$product->status = '1';
        //$product->save();

        $lazada['category'] = $data['lazada_cate_code'];
        if (!$lazada['category']) {
            return '<script>alert("카테고리를 설정하세요");history.back();</script>';
        }

        $lazada['title'] = $data['detail_title'];
        $lazada['brand'] = Auth::user()->brand_lazada;
        $lazada['model'] = "Not Specified";

        $sku = array();
        $sku_count = 0;
        $skus_xml='';

        $migrate_xml = Storage::disk('local')->get('xml/lazada/MigrateImage.xml');
        $sku_xml_ori = Storage::disk('local')->get('xml/lazada/sku.xml');
        $cp_xml_ori = Storage::disk('local')->get('xml/lazada/CreateProduct.xml');
        

        $productOptionCount = count($productOptions);
        $productOptionNowCount=0;
        $resultUpload = '결과 [';
        foreach ($productOptions as $key=>$productOption) {
            
            //가격정보가 없는 세부옵션품목은 넘어간다.
            if (!$productOption->price) continue;

            ///////////////////////////////////////////////////////////////////////////
            // 1. 이미지 업로드
            $action = "MigrateImage";
            $parameters = Helper::lazadaCreateParameter(Auth::user()->id_lazada, Auth::user()->api_lazada, $action);

            $images = explode('|', $productOption->image);

            $url = "https://api.sellercenter.lazada.com.my?Action=$action&Format=json&Timestamp=".rawurlencode($parameters['Timestamp'])."&UserID=".rawurlencode($parameters['UserID'])."&Version=1.0&Signature=".$parameters['Signature'];

            //$ret = Helper::lazadaAPIexecute($url, $action, $uid);

            $limit_cnt=0;
            $sku_image = '';
            $successUploadImage='0';
            foreach ($images as $image) {
                
                if (!$image) continue;
                if (++$limit_cnt > 8) break; //라자다 이미지 갯수제한 8개

                $image = str_replace("%2B", "+", $image);

                $image_url = str_replace('|IMAGE|', $image, $migrate_xml);

                Storage::disk('local')->put("user/$uid/xml/$action.xml", $image_url);
                
                $ret = Helper::lazadaAPIexecute($url, $action, $uid);
                Debugbar::info($ret);

                if (strpos($ret, 'IMAGE_DIMENSION_TOO_SMALL') > 0) {
                    $resultUpload.= $limit_cnt."번 이미지 크기가 너무 작아서 업로드 오류발생\\n ";
                    continue;
                }
                //Sleep(1);
                $successUploadImage++;

                $lazada_url = Helper::subsearch($ret, '"Url": "', '"', true);

                //$sku[$sku_count][] = ['lazada_url' => $lazada_url];

                if ($lazada_url) {
                    $sku_image[] =  $lazada_url;
                }
                else 
                    continue;

            }

            ///////////////////////////////////////////////////////////////////////////
            // 2. sku 조합

            $sellerSku = $productOption->asin.'-'.$sku_count;

            $image_cnt = count($sku_image);

            if (isset($sku_image) == false || $successUploadImage == 0) {
                //이미지가 없으면 실패처리하고 
                continue;
            }

            Debugbar::info($image_cnt);
            Debugbar::info($sku_image);
            $sku_image = array_unique($sku_image); //이미지 파일 중복 제거

            $sku_image_xml='';
            for ($i=0;$i<$image_cnt;$i++) {
                $sku_image_xml .= '<Image>'.$sku_image[$i].'</Image>';
            }
            
            //$sku_image_xml .= '<Image>https://my-live.slatic.net/original/1c93ff736bb1d4030173e2ce3660d697.jpg</Image>'; //테스트용

            $optionColor = Helper::getLazadaOptionColor($productOption->option_2);
            if (!$optionColor) {
                $optionColor = "Not Specified";
            }

            $sku_xml = $sku_xml_ori;
            $sku_xml = str_replace('|IMAGE|', $sku_image_xml, $sku_xml);
            $sku_xml = str_replace('|SKU|', $sellerSku, $sku_xml);
            $sku_xml = str_replace('|DIMENSIONS|', $product->dimensions, $sku_xml);
            $sku_xml = str_replace('|COLOR|', $optionColor, $sku_xml);

            $priceXML='';
            $price = str_replace('$', '', $productOption->price);
            $listPrice = str_replace('$', '', $productOption->list_price);
            if ($listPrice && $listPrice-$price > 0) {
                
                $priceXML = '<price>'.$listPrice.'</price>';
                $priceXML.= '<special_price>'.$price.'</special_price>';
                $priceXML.= '<special_from_date>'.Carbon::now()->format('Y-m-d').'</special_from_date>';
                $priceXML.= '<special_to_date>2026-10-18</special_to_date>';
            }
            else {
                $priceXML = '<price>'.$price.'</price>';
            }
            $sku_xml = str_replace('|PRICE|', $priceXML, $sku_xml);

            $skus_xml .= $sku_xml;

            //옵션이 있는값은 sku를 중첩해서 만들며 맨 마지막에는 글쓰기로 들어간다
            
            if(++$productOptionNowCount === $productOptionCount) {
               //last index
            }
            else {
                if ($optionColor != "Not Specified") {
                    continue;
                }
            }

            ///////////////////////////////////////////////////////////////////////////
            // 3. 라자다 글쓰기

            // 상품에 포함된 옵션이 Color 나 Size가 아닐경우 단품으로 일일이 등록한다.
            
            $action = "CreateProduct";
            $parameters = Helper::lazadaCreateParameter(Auth::user()->id_lazada, Auth::user()->api_lazada, $action);
            
            $cp_xml = $cp_xml_ori;
            $cp_xml = str_replace('|CATEGORY|', $lazada['category'], $cp_xml);
            $cp_xml = str_replace('|TITLE|', $lazada['title'], $cp_xml);
            $cp_xml = str_replace('|DESCRIPT|', htmlspecialchars($product->contents), $cp_xml);
            $cp_xml = str_replace('|SHORT|', $lazada['title'], $cp_xml);
            $cp_xml = str_replace('|BRAND|', "None", $cp_xml);
            //$cp_xml = str_replace('|BRAND|', $lazada['brand'], $cp_xml);
            $cp_xml = str_replace('|MODEL|', $lazada['model'], $cp_xml);
            $cp_xml = str_replace('|OTHER_OPTION|', Helper::getLazadaCategoryOtherOption($lazada['category']), $cp_xml);
            $cp_xml = str_replace('|SKU|', $skus_xml, $cp_xml);
            
            Storage::disk('local')->put("user/$uid/xml/$action.xml", $cp_xml);

            $url = "https://api.sellercenter.lazada.com.my?Action=$action&Format=json&Timestamp=".rawurlencode($parameters['Timestamp'])."&UserID=".rawurlencode($parameters['UserID'])."&Version=1.0&Signature=".$parameters['Signature'];

            $ret = Helper::lazadaAPIexecute($url, $action, $uid);

            $ret_json = Helper::subsearch($ret, '{', '}}}', false);
            
            $ret_json = json_decode($ret_json, true);

            Debugbar::info($ret_json);

            if (array_key_exists('SuccessResponse', $ret_json)) {
                $resultUpload.= "성공 ";
                $product->status = '1';
                $product->save();
                
            }
            else if (array_key_exists('ErrorResponse', $ret_json)) {

                //print_r($cp_xml);
                print_r($ret_json);
                exit;
                //Debugbar::info($ret_json['ErrorResponse']['Body']['Errors'][0]['Message']);
                if (array_key_exists('Body', $ret_json)) 
                    return '<script>alert("실패:'.$ret_json['ErrorResponse']['Body']['Errors'][0]['Message'].'");location.replace("/goods/view/'.$product_id.'");</script>';
                else 
                    return '<script>alert("실패:'.$ret_json['ErrorResponse']['Head']['ErrorMessage'].'");location.replace("/goods/view/'.$product_id.'");</script>';
            }
            else { 
                return '<script>alert("실패: 알려지지않은 오류");location.replace("/goods/view/'.$product_id.'");</script>';
            }

            if($productOptionNowCount === $productOptionCount) {
                break;
             }
             else {
                $skus_xml = '';
                continue;
             }

            // $skus_xml = '';
            // if (next($productOptions) == false) {
            //     if (array_key_exists('SuccessResponse', $ret_json)) {
            //         Debugbar::info('성공');
            //         $product->status = '1';
            //         $product->save();
            //         return '<script>alert("성공되었습니다");history.back();</script>';
                    
            //     }
            //     else if (array_key_exists('ErrorResponse', $ret_json)) {
            //         print_r($cp_xml);
            //         print_r($ret_json);
            //         exit;
            //         //Debugbar::info($ret_json['ErrorResponse']['Body']['Errors'][0]['Message']);
            //         if (array_key_exists('Body', $ret_json)) 
            //             return '<script>alert("실패:'.$ret_json['ErrorResponse']['Body']['Errors'][0]['Message'].'");location.replace("/goods/view/'.$product_id.'");</script>';
            //         else 
            //             return '<script>alert("실패:'.$ret_json['ErrorResponse']['Head']['ErrorMessage'].'");location.replace("/goods/view/'.$product_id.'");</script>';
            //     }
            //     else { 
            //         return '<script>alert("실패: 알려지지않은 오류");location.replace("/goods/view/'.$product_id.'");</script>';
            //     }
            // }
        }
        $resultUpload.="]";
        return "<script>alert('$resultUpload');history.back();</script>";
        
        
    }
    
    public function goodsCheckDetailOption(Request $request, $product_id='0')
    {
        // $xml = Storage::disk('local')->get('xml/lazada/category.json');
        // $xml = json_decode($xml, true);
        // DebugBar::info($xml['SuccessResponse']['Body']);
        
        $image = '';
        $options = ProductOption::where('parent_id', $product_id)
                                ->orderBy('id', 'desc')
                                ->get();
        $headers = ['Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8', 
                    'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
                    'Accept-Language'=>'ko,en-US;q=0.8,en;q=0.6,zh-CN;q=0.4,zh;q=0.2',
                    'Referer'=>'https://www.amazon.com/'
                    ];
                    
        foreach ($options as $option) {
            
            $url = 'https://www.amazon.com/gp/product/'.$option->asin.'/ref=sspa_dk_details_2?psc=1';

            DebugBar::info($url);

            $client = new Client(); //GuzzleHttp\Client
            //$client->getConfig()->set('curl.options', array(CURLOPT_VERBOSE => true));

            $result = $client->request('GET', $url, $headers);
            $data = $result->getBody();

            $price = trim(Helper::subsearch($data, 'id="priceblock_ourprice" class="a-size-medium a-color-price">', '</span>', true));
            
            $listPrice = trim(Helper::subsearch($data, '<span class="a-text-strike">', '</span>', true));
            
            $images = Helper::subsearch($data, "'colorImages': { 'initial': ", "}]},", true);
            $images.= "}]";
            $images = json_decode($images, true);
            DebugBar::info($images);

            $image = '';
            foreach ($images as $temp) {
                $image .= $temp['large'].'|';
            }

            DebugBar::info('price:'.$price);
            DebugBar::info('list_price:'.$listPrice);
            
            $update = ProductOption::find($option->id);
            $update->list_price = $listPrice;
            $update->price = $price;
            $update->image = $image;
            $update->save();

            Sleep(5);
            
        }
        return redirect('/goods/view/'.$product_id);
        
    }

    public function managementMember(Request $request)
    {
        $users = User::orderBy('id', 'desc')->paginate(10);
        
        return view('management.member')->with('cate', '2')
                                        ->with('users', $users);
    }

    public function userDetailEdit(Request $request)
    {
        $data=$request->all();
        $user = User::find(Auth::user()->id);

        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->id_lazada = $data['id_lazada'];
        $user->api_lazada = $data['api_lazada'];
        $user->brand_lazada = $data['brand_lazada'];
        $user->api_amazon = $data['api_amazon'];
        $user->save();

        return redirect('/management/userDetail');
        
    }
}
