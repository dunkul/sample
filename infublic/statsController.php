<?php

namespace App\Http\Controllers\stats;

use App\Facades\ReturnData;
use App\Model\campaign;
use App\Model\campaign_channel;
use App\Model\channel;
use App\Model\channel_log;
use App\Model\config;
use App\Model\post;
use App\Model\post_log;
use App\Model\campaign_complete_log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Carbon\Carbon;
use Debugbar;
class StatsController extends Controller
{
    public function post_stats(Request $request,$id,$id1,$type='day')
    {
        //완성된 캠페인이 있을 경우엔 저장된 데이터를 바로 불러온다 20180813
        if (!$request->has('complete_campaign')) {
            if ($request->input('page') == "summary") {

                $query = DB::table('campaign_complete_log')->where('campaign_no', $id1)->select('report_summary_data')->first();
                if ($query && $query->report_summary_data) {

                    $result = json_decode($query->report_summary_data, true);
                    return  ReturnData::getData($result)->json_data('200');
                }
            }
            else if ($request->input('page') == "channel") {
                $query = DB::table('campaign_complete_log')->where('campaign_no', $id1)->select('report_channel_data')->first();
                if ($query && $query->report_channel_data) {

                    $result = json_decode($query->report_channel_data, true);
                    return  ReturnData::getData($result)->json_data('200');
                }
            }
            else if ($request->input('page') == "time") {
                $query = DB::table('campaign_complete_log')->where('campaign_no', $id1)->select('report_time_data')->first();
                if ($query && $query->report_time_data) {

                    $result = json_decode($query->report_time_data, true);
                    return  ReturnData::getData($result)->json_data('200');
                }
            }
        }

        $campaign=campaign::where('campaign_no', $id1)->where('user_id', $id)->select('follower', 'charge_total', 'compensation_money', 'channel_quantity')->first();

        // $check = campaign::where('campaign_no', $id1)->where('user_id', $id)->count();
        if (!isset($campaign)) {
            $user_id = Session::get('user_id');
            $type = substr($user_id,0,1);
            if ($type !== "M")
                return  ReturnData::getMsg('잘못된 권한 접근입니다. URL 확인하시기 바랍니다.')->json_data(400);

            $campaign=campaign::where('campaign_no', $id1)->select('follower', 'charge_total', 'compensation_money', 'channel_quantity')->first();
            
        }

        $campaign_channel=campaign_channel::where('campaign_no', $id1)->whereIn('status', ['게재', '수정요청', '검수중', '완료', '종료'])->select("channel_no")->get();
        
        if ($campaign_channel->count() == 0) { //붙은 채널이 없으면 그냥 나가자

            $result = ['channel'=>'', 'chart'=>'', 'member'=>'', 'date'=>'', 'day_data'=>'', 'time_table'=>'', 'data'=>'', 'campaign_price'=>'', 'campaign_channel_count'=>'', 'channel_post_total'=>'', 'boundary_follower'=>'', 'buzz'=>''];
            return  ReturnData::getData($result)->json_data(200);
        }

        Debugbar::startMeasure('post_stats','post_stats');
        
        // $count = $campaign_channel->count();
        // $where = '';
        //->whereRaw(DB::raw("date_add(end_date, interval +$sc->daytime $sc->kind) <= '{$this->now_time}'"))

        // $where = '( ';
        // for ($i=0;$i<$count;$i++) {
        //     $where .= 'T1.channel_no = "'.$campaign_channel[$i]->channel_no.'" or ';
        // }
        // $where .= '1=2 )';
        // DebugBar::info($where);

        $query = post_log::from('post_log as T1')
            ->where('T1.campaign_no',$id1)
            //->whereRaw($where)
            ->whereIn('T1.channel_no', $campaign_channel)
            ->where('post_yn','사용')
            ->orderby('T1.regdate','DESC')
            ->select('T1.post_log_no'
                ,'T1.user_id'
                ,'T1.channel_no'
                ,'T1.channel_uid'
                ,'T1.platform'
                ,DB::raw("(select follower from channel_log where channel_no=T1.channel_no and date(T1.regdate)=date(regdate) order by regdate desc limit 1) AS follower")
                ,'T1.post_no'
                ,'T1.post_show'
                ,'T1.post_type'
                //,'T1.post_caption'
                ,'T1.created_time'
                ,'T1.regdate'
                ,'T1.post_url'
                ,'T1.like'
                ,'T1.comment'
                ,'T1.views'
                ,'T1.share'
                ,'T1.post_status'
        );

        if($request->input('page') === "excel_down") { //원본 총 데이터

            $query->addSelect(DB::raw("(select platform_url from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS platform_url"));
            $query->addSelect(DB::raw("(select channel_status from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS channel_status"));
            $query->addSelect(DB::raw("(select regdate from channel_log where channel_no=T1.channel_no and date(T1.regdate)=date(regdate) order by regdate desc limit 1) AS follower_regdate"));
            $query = $query->get();
            
            $this->excel_down($query);
            return  ReturnData::getData()->json_data(200);
        }

        $query = $query->get();

        Debugbar::stopMeasure('post_stats');

        $channel_data = [];

        //기본데이타 구조 만들기


        if($request->input('page') == "channel") { //채널 통계

            foreach ($query as $key=>$val) {
                $regdate = (int)str_replace("-","",substr($val['regdate'],0,10));
                $time = substr($val['regdate'],11,2);

                //채널별 통계
                $channel_data[$val['channel_no']][$regdate][$time]['comment']=$val['comment'];
                $channel_data[$val['channel_no']][$regdate][$time]['like']=$val['like'];
                $channel_data[$val['channel_no']][$regdate][$time]['follower']=$val['follower'];
                $channel_data[$val['channel_no']][$regdate][$time]['view']=$val['views'];
            }
            //채널별 최근 데이타
            foreach ($channel_data as $key=>$val) {
                //최신날짜 포함
                //$channel[$k][key($v)][] = max($v[key($v)]);
                //$result[$k]['comment']=array_sum($v[max(array_keys($v))]['comment']);
                
                //채널 기본 데이타
                $dt = max(array_keys($val)); //키의 가장 큰값.. 지난날의 경우엔 23, 당일은 당일 현재시간

                $channel[$key] = $val[$dt][max(array_keys($val[$dt]))];  //가장마지막시간 20190104
                //$channel[$key] = max($val[key($val)]);  //하루중 제일큰값

                //채널 차트 데이타 구하기
                foreach($val as $k=>$v) {
                    $channel_chart[$key][$k] = max($v);
                }
            }
            
            if (!isset($channel)) { //채널이 올린 게시물이 없다면 그냥 나감
                $result = ['channel'=>'','chart'=>'','member'=>''];
    
                return  ReturnData::getData($result)->json_data(200);
            }

            //채널 가져오기
            $y = date('Y');
            $query=channel::from('channel AS T1')
                ->leftjoin('user AS T3','T3.user_id','=','T1.user_id')
                ->leftjoin('user_profile as T4', 'T4.user_id', '=', 'T1.user_id')
                ->select(
                    "T1.channel_no"
                    ,"T1.channel_uid"
                    ,"T4.birth as birthday"
                    ,"T4.gender"
                    // ,"T3.address1"
                    ,"T3.sido"
                    ,DB::raw("(IF(T4.birth > 0,{$y} - SUBSTRING(T4.birth,1,4),null)) AS age")
                    //,DB::raw("(select name from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS name") //20180604 tomato
                    ,DB::raw("(select picture_url from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS picture_url")
                    ,DB::raw("(select post_url from post where campaign_no='$id1' and channel_no=T1.channel_no and post_yn='사용' and sc_post_date is null order by regdate desc limit 1) AS post_url")
                )
                ->whereIn('T1.channel_no',array_keys($channel))
                ->get();
            //dd($query);
            foreach ($query as $_v){
                $member[$_v['channel_no']]['gender'] = $_v['gender'];
                $member[$_v['channel_no']]['address'] = $_v['sido'];
                $member[$_v['channel_no']]['age'] = $_v['age'];
                $member[$_v['channel_no']]['name'] = $_v['name'];
                $member[$_v['channel_no']]['picture_url'] = $_v['picture_url'];
                $member[$_v['channel_no']]['channel_uid'] = $_v['channel_uid'];
                $member[$_v['channel_no']]['post_url'] = $_v['post_url'];
            }
            //dd($member);

            $result = ['channel'=>$channel,'chart'=>$channel_chart,'member'=>$member];

            if ($request->has('complete_campaign') && $request->input('complete_campaign') == true) {
                $json = json_encode($result);
                $ret = campaign_complete_log::updateOrCreate(['campaign_no'=>$id1, 'user_id'=>$id], ['report_channel_data'=>$json]);
            }
        }
        else if($request->input('page') == "time" ) //시간통계
        {
            $data_kind = ['like', 'comment', 'views'];

            $now_day = Carbon::now()->format('Ymd');
            $now_hour = Carbon::now()->format('H');
            $after_day = Carbon::now()->addDays(1)->format('Ymd');

            $date = array();
            $day_data = array();
            $date_data = array();

            //기본데이타 구조 만들기
            foreach ($query as $key=>$val) {
                $basic_date = Carbon::createFromFormat('Y-m-d H:i:s', $val['regdate']);
                $regdate = $basic_date->format('Ymd'); 
                $time = $basic_date->format('H');

                if (!isset($date_data[$regdate][$time]['channel_no'])) $date_data[$regdate][$time]['channel_no'][] = 0;
                if (!isset($date_data[$regdate][$time]['follower'])) $date_data[$regdate][$time]['follower'] = 0;

                foreach ($data_kind as $_v) {
                    if (!isset($date_data[$regdate][$time][$_v])) $date_data[$regdate][$time][$_v] = 0;
                }

                if (!in_array($val['channel_no'], $date_data[$regdate][$time]['channel_no'])) { //해당하는 날짜 시간에 채널정보는 하나만 넣는다 20180607 tomato
                    
                    $date_data[$regdate][$time]['channel_no'][] = $val['channel_no'];
                    $date_data[$regdate][$time]['follower']+=$val['follower'];
                    
                    foreach ($data_kind as $_v) {
                        $date_data[$regdate][$time][$_v]+=$val[$_v];
                    }
                }
            }

            $date_time_table = array();
            $date_time_table_total = array();

            ksort($date_data);
            
            //날짜별로 시간대마다 값 차이를 계산한다
            $startZeroDate=true;
            $startDate=true;
            
            foreach ($date_data as $regdates => $dates) {
                
                foreach ($dates as $times => $values) {

                    if ($times == '00') { //00시는 전날 23시 값을 뺀다. 전날 23시 값이 없으면 0
                        
                        $before_day = Carbon::createFromFormat('Ymd', $regdates)->subDays(1)->format('Ymd');
                        foreach ($data_kind as $_v) {
                            if (isset($date_data[$before_day]['23'][$_v]))
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$before_day]['23'][$_v];
                            else {
                                if ($startZeroDate === false) {
                                    $date_time_table[$regdates][$times][$_v] = 0; 
                                }
                                else {
                                    $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v]; 
                                }
                            }
                        }

                        /////////////////////////////////////////////////
                        //주간데이터는 당일 00시에서 전날 00시 값을 뺀다. 
                        $now_day = Carbon::now()->format('Ymd');
                        $now_hour = Carbon::now()->format('H');
                        $after_day = Carbon::now()->addDays(1)->format('Ymd');

                        foreach ($data_kind as $_v) {
                            if (isset($date_data[$before_day]['00'][$_v])) {
                                $day_data[$regdates][$_v] = $date_data[$regdates]['00'][$_v] - $date_data[$before_day]['00'][$_v];
                            }
                            else { //전날값이 없을경우
                                if ($startZeroDate === false) { //변동량은 0
                                    $day_data[$regdates][$_v] = 0;
                                }
                                else {  //첫날일 경우 첫날 데이터
                                    $day_data[$regdates][$_v] = $date_data[$regdates]['00'][$_v];
                                }
                            }
                        }
                        
                        //당일날짜 현재시간까지 연산하기 위해서 추가 20181207
                        if ((int)$now_hour > 1 && $now_day == $regdates && isset($date_data[$now_day]['00'][$_v]) ) {
                            foreach ($data_kind as $_v) {
                                $day_data[$after_day][$_v] = @$date_data[$now_day][$now_hour][$_v] - @$date_data[$now_day]['00'][$_v];
                            }
                        }


                        $startZeroDate = false;

                        //날짜별 데이터는 00시 기준데이터로 저장.
                        $date[$regdates] = $date_data[$regdates]['00'];
                        
                    }
                    else if ($times == '23') { //게시물 수집 마지막날은 23시까지 크롤링이 되기때문에 이날은 00시에 하던 수치 계산을 23시에 해야한다. 20181130
                        $before_hour = str_pad((($times*1) - 1), 2, '0', STR_PAD_LEFT);
                        foreach ($data_kind as $_v) {
                            if (isset($date_data[$regdates][$before_hour][$_v])) 
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$regdates][$before_hour][$_v];
                            else {
                                if ($startDate === false) {
                                    $date_time_table[$regdates][$times][$_v] = 0;
                                }
                                else {
                                    $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v];
                                }
                            }
                        }

                        $after_day = Carbon::createFromFormat('Ymd', $regdates)->addDays(1)->format('Ymd');
                        if (!isset($date_data[$after_day]['00'][$_v])) { //다음날 00시 값이 없는 이유는 1.크롤링이 끝남 2. 아직 12시가 안됨.
                            $date[$after_day] = $date_data[$regdates]['23'];
                        }
                    }
                    else { //01~22시까지의 값은 지금값에서 한시간전의 값을 뺀다. 
                        $before_hour = str_pad((($times*1) - 1), 2, '0', STR_PAD_LEFT);
                        foreach ($data_kind as $_v) {
                            if (isset($date_data[$regdates][$before_hour][$_v])) 
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$regdates][$before_hour][$_v];
                            else {
                                if ($startDate === false) {
                                    $date_time_table[$regdates][$times][$_v] = 0;
                                }
                                else {
                                    $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v];
                                }
                            }
                        }

                        //00시에 일간데이터를 연산하기때문에 당일 날짜의 데이터가 없다.(00시가 지나지 않았기 때문). 하여 가장 최근 뽑아낸 시간을 기준으로 당일 데이터를 연산 20181207
                        $now_day = Carbon::now()->format('Ymd');
                        $now_hour = Carbon::now()->format('H');
                        $after_day = Carbon::now()->addDays(1)->format('Ymd');
                        
                        if ((int)$now_hour > 1 && $regdates == $now_day && $times == $now_hour) {
                            $date[$after_day] = $date_data[$now_day][$now_hour];
                        }
                    }

                    $date_time_table[$regdates][$times]['follower'] = $date_data[$regdates][$times]['follower'];
                }
                
                $startDate = true;
            }
            //DebugBar::info($date_time_table);
            //Debugbar::stopMeasure('time1');
            
            // DebugBar::info($date_data);
            // Debugbar::startMeasure('time2','time2');
            
            $date_time_table_total = array();
            
            //날짜별로 분류된 값들을 시간대별로 합산한다
            foreach ($date_time_table as $regdates => $dates) {

                foreach ($dates as $times => $values) {
                    
                    foreach ($data_kind as $_v) {
                        if (!isset($date_time_table_total[$times][$_v])) $date_time_table_total[$times][$_v] = 0;
                        $date_time_table_total[$times][$_v] += $date_time_table[$regdates][$times][$_v];
                    
                    }

                    if (!isset($date_time_table_total[$times]['follower']) || $date_time_table_total[$times]['follower'] < $date_data[$regdates][$times]['follower'] )
                        $date_time_table_total[$times]['follower'] = $date_data[$regdates][$times]['follower'];
                    
                }                
            }

            // Debugbar::stopMeasure('time2');

            DebugBar::info($date_time_table);

            $result = ['date'=>$date, 'day_data'=>$day_data, 'time_table'=> $date_time_table_total];

            if ($request->has('complete_campaign') && $request->input('complete_campaign') == true) {
                $json = json_encode($result);
                $ret = campaign_complete_log::updateOrCreate(['campaign_no'=>$id1, 'user_id'=>$id], ['report_time_data'=>$json]);
            }
        }
        else  //요약및 퍼포먼스 일부
        {

            foreach ($query as $key=>$val) {
                $basic_date = Carbon::createFromFormat('Y-m-d H:i:s', $val['regdate']);
                $regdate = $basic_date->format('Ymd'); 
                $time = $basic_date->format('H');

                //날짜별 채널 카운트
                $date_channel[$regdate][]=$val['channel_no'];

                //날짜별 통계
                @$channel_data[$regdate][(int)$time]['comment'][]=$val['comment'];
                @$channel_data[$regdate][(int)$time]['like'][]=$val['like'];
                @$channel_data[$regdate][(int)$time]['follower'][]=$val['follower'];
                @$channel_data[$regdate][(int)$time]['view'][]=$val['views'];
            }

            //가장 높은시간에 데이타 가져오기
            @ksort($channel_data);

            $isFirst = true;
            
            foreach ($channel_data as $k=>$v) {
                //@dump(max(array_keys($v)));
                //$result[$k] = $v[max(array_keys($v))];
                //현재 키값을 맥스(23시)로 잡아서 0시로 계산되는 시간리포트나 엑셀과 맞지않음. 전체적인 변경이 필요함. 20181130
                //min으로 변경시 첫날값은 없앤다. 날짜는 하루 당긴다.
                //마지막날은 맥스키값을 찾아서 가져온다.

                if ($isFirst == true) {
                    $isFirst = false;
                    continue;
                }
                $dt = Carbon::createFromFormat('Ymd', $k)->subDay()->format('Ymd');
                $result[$dt]['comment']=array_sum($v[min(array_keys($v))]['comment']);
                $result[$dt]['like']=array_sum($v[min(array_keys($v))]['like']);
                $result[$dt]['view']=array_sum($v[min(array_keys($v))]['view']);
                $result[$dt]['follower']=array_sum($v[min(array_keys($v))]['follower']);

            }
            $max_date = max(array_keys($channel_data));
            $max_hour = max(array_keys($channel_data[$max_date]));
            
            $result[$max_date]['comment']=array_sum($channel_data[$max_date][$max_hour]['comment']);
            $result[$max_date]['like']=array_sum($channel_data[$max_date][$max_hour]['like']);
            $result[$max_date]['view']=array_sum($channel_data[$max_date][$max_hour]['view']);
            $result[$max_date]['follower']=array_sum($channel_data[$max_date][$max_hour]['follower']);
            
            //Organic Buzz(해시태그 데이터) 가져오기
            $buzz_result = $this->get_organic_buzz($id, $id1);

            $result=[
                'data'=>$result,
                'campaign_price'=>isset($campaign->charge_total)?$campaign->charge_total:0,
                'campaign_channel_count'=>isset($campaign->channel_quantity)?$campaign->channel_quantity:0,
                'channel_post_total'=>$campaign_channel->count(),
                'boundary_follower'=>isset($campaign->follower) ? $campaign->follower : 0,
                'buzz'=>$buzz_result,
                'campaign_type'=>(isset($query[0]->post_type) ? $query[0]->post_type : '이미지')
            ];

            DebugBar::info($result);
            
            //퍼포먼스 채널별 카운터 추가
            // if ($request->input('page') == "performance") {
                //채널 날짜별 포스트 카운터
                // if (isset($date_channel)) {
                //     foreach ($date_channel as $k=>$v) {
                //         $channel_count[$k]['cpc']=count(array_unique($v));
                //     }
                //     $result['channel_post']=$channel_count;
                // }
            // }

            if ($request->has('complete_campaign') && $request->input('complete_campaign') == true) {
                $json = json_encode($result);
                $ret = campaign_complete_log::updateOrCreate(['campaign_no'=>$id1, 'user_id'=>$id], ['report_summary_data'=>$json]);
            }
        }


        //http_response_code(500);dd($result);
        return  ReturnData::getData($result)->json_data(200);
    }

    public function get_organic_buzz($id, $id1)
    {
        $send = new Request();

        $hash = $this->hashtag_view($send, $id, $id1);
        $hash = json_decode($hash->getContent());
        $hash = $hash->data->hash_data;
        
        $buzz_data = array();

        foreach ($hash as $key=>$value) {
            $cnt = count($value->tag_count);
            for ($i=$cnt-1;$i>=0;$i--) {
                $regdate=substr($value->regdate[$i], 0, 10);

                if (!isset($buzz_data[$regdate])) {
                    $buzz_data[$regdate]['total'] = 0;
                    $buzz_data[$regdate]['campaign'] = 0;
                }
                if ($i >= 1) {
                    $buzz_data[$regdate]['campaign'] += $value->tag_count[$i] - $value->tag_count[$i-1];
                }
                else {
                    $buzz_data[$regdate]['campaign'] = 0;
                }
                $buzz_data[$regdate]['total'] += $value->tag_count[$i];
            }
        }

        $buzz_result = array();
        foreach ($buzz_data as $key => $value) {
            $buzz_result[$key]  = $value['campaign'];
            // $buzz_result[$key]  = $value['total'] - $value['campaign'];
        }
        ksort($buzz_result);
        return $buzz_result;
    }

    //날짜별 최근 채널 데이타 엑셀
    public function post_stats_excel(Request $request,$id,$id1)
    {
        $campaign_channel=campaign_channel::where('campaign_no', $id1)->whereIn('status', ['게재', '수정요청', '검수중', '완료', '종료'])->select("channel_no")->get();
        $campaign_channel_count = $campaign_channel->count();

        $query = post_log::from('post_log as T1')
            ->where('T1.campaign_no',$id1)
            ->whereIn('T1.channel_no', $campaign_channel)
            ->where('post_yn','사용')
            ->orderby('T1.post_log_no','DESC')
            ->select('T1.post_log_no'
                ,'T1.user_id'
                ,'T1.channel_no'
                ,'T1.channel_uid'
                ,'T1.platform'
                ,DB::raw("(select follower from channel_log where channel_no=T1.channel_no and date(T1.regdate)=date(regdate) order by regdate desc limit 1) AS follower")
                ,'T1.post_no'
                ,'T1.post_show'
                ,'T1.post_type'
                ,'T1.created_time'
                ,'T1.regdate'
                ,'T1.post_url'
                ,'T1.like'
                ,'T1.comment'
                ,'T1.share'
                ,'T1.post_status'
                ,'T1.views'
        );

        // $query->addSelect(DB::raw("(select platform_url from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS platform_url"));
        // $query->addSelect(DB::raw("(select channel_status from channel_log where channel_no=T1.channel_no order by regdate desc limit 1) AS channel_status"));
        //$query->addSelect(DB::raw("(select regdate from channel_log where channel_no=T1.channel_no and date(T1.regdate)=date(regdate) order by regdate desc limit 1) AS follower_regdate"));
        
        $query = $query->get();
        
        $campaign_table=campaign::find($id1);

        //기본데이타 구조 만들기
        foreach ($query as $key=>$val) {
            $regdate=(int)str_replace("-", "", substr($val['regdate'], 0, 10));
            $time=substr($val['regdate'], 11, 2);

            //날짜별 채널 카운트
            $date_channel[$regdate][]=$val['channel_no'];

            //날짜별 통계
            @$channel_data[$regdate][(int)$time]['comment'][]=$val['comment'];
            @$channel_data[$regdate][(int)$time]['like'][]=$val['like'];
            @$channel_data[$regdate][(int)$time]['views'][]=$val['views'];
            @$channel_data[$regdate][(int)$time]['follower'][]=$val['follower'];

        }
        
        //가장 높은시간에 데이타 가져오기
        foreach ($channel_data as $k=>$v) {
            //@dump(max(array_keys($v)));
            //$result[$k] = $v[max(array_keys($v))];
            $result[$k]['comment']=array_sum($v[max(array_keys($v))]['comment']);
            $result[$k]['like']=array_sum($v[max(array_keys($v))]['like']);
            $result[$k]['views']=array_sum($v[max(array_keys($v))]['views']);
            $result[$k]['follower']=array_sum($v[max(array_keys($v))]['follower']);
        }
        
        $keys = array_keys($result); 
        $engagement = $result[$keys[0]]['comment']+$result[$keys[0]]['like'];

        // $sdate = Carbon::createFromFormat('Y-m-d 00:00:00', $campaign_table->sdate)->addDays($campaign_table->channel_edate)->format('Y-m-d');
        $sdate = Carbon::createFromFormat('Y-m-d 00:00:00', $campaign_table->sdate)->format('Y-m-d');
        $edate = Carbon::createFromFormat('Y-m-d 00:00:00', $campaign_table->timeline)->subDays(1)->format('Y-m-d');
        $campaign_date = $sdate.' ~ '.$edate;

        $buzz_result = $this->get_organic_buzz($id, $id1);
        $buzz_result = array_sum($buzz_result);
        $buzz_result -= $campaign_channel_count;

        $campaign = array_merge($campaign_table->toArray(), ['campaign_date'=>$campaign_date, 
                                                        'channel_count'=>$campaign_channel_count,
                                                        'follower_total'=>$result[$keys[0]]['follower'],
                                                        'engagement'=>$engagement,
                                                        'engagement_rate'=>$engagement/$result[$keys[0]]['follower'],
                                                        'like'=>$result[$keys[0]]['like'],
                                                        'comment'=>$result[$keys[0]]['comment'],
                                                        'views'=>$result[$keys[0]]['views'],
                                                        'cost_per_channel'=>$campaign_channel_count > 0 ? $campaign_table->charge_total/$campaign_channel_count : 0,
                                                        'cost_per_follower'=>$result[$keys[0]]['follower'] > 0 ? $campaign_table->charge_total/$result[$keys[0]]['follower'] : 0,
                                                        'cost_per_engagement'=>$engagement > 0 ? $campaign_table->charge_total/$engagement : 0,
                                                        'cost_per_like'=>$result[$keys[0]]['like'] > 0 ? $campaign_table->charge_total/$result[$keys[0]]['like'] : 0,
                                                        'cost_per_comment'=>$result[$keys[0]]['comment'] > 0 ? $campaign_table->charge_total/$result[$keys[0]]['comment'] : 0,
                                                        'boundary_follower'=>isset($campaign_table->follower) ? $campaign_table->follower : 0,
                                                        'organic_buzz'=>$buzz_result,
                                                        ]);
        
        ////////////////////////////////////////////////////////////////////////////////////////////////
        //시간

        $date_data = array();
        $date = array();        //각 날짜에 보이는 토탈값.
        $day_data = array();    //각 날짜에 생성된 값. date변수를 가지고 연산하여 뽑는다
        $data_kind = ['like', 'comment', 'views'];

        //기본데이타 구조 만들기
        foreach ($query as $key=>$val) {
            $basic_date = Carbon::createFromFormat('Y-m-d H:i:s', $val['regdate']);
            $regdate = $basic_date->format('Ymd'); 
            $time = $basic_date->format('H');

            if (!isset($date_data[$regdate][$time]['channel_no'])) $date_data[$regdate][$time]['channel_no'][] = 0;
            if (!isset($date_data[$regdate][$time]['follower'])) $date_data[$regdate][$time]['follower'] = 0;

            foreach ($data_kind as $_v) {
                if (!isset($date_data[$regdate][$time][$_v])) $date_data[$regdate][$time][$_v] = 0;
            }

            if (!in_array($val['channel_no'], $date_data[$regdate][$time]['channel_no'])) { //해당하는 날짜 시간에 채널정보는 하나만 넣는다 20180607 tomato
                
                $date_data[$regdate][$time]['channel_no'][] = $val['channel_no'];
                $date_data[$regdate][$time]['follower']+=$val['follower'];
                
                foreach ($data_kind as $_v) {
                    $date_data[$regdate][$time][$_v]+=$val[$_v];
                }
            }
        }
        
        $date_time_table = array();
        $date_time_table_total = array();

        ksort($date_data);
        
        //날짜별로 시간대마다의 파라미터값 차이를 계산한다
        $startZeroDate=true;    //00시 첫날인가
        $startDate=true;        //00시 제외 첫날인가. 시작할때는 첫날이므로 true, for 한바퀴 돌면 그때부터는 false

        foreach ($date_data as $regdates => $dates) {
            
            foreach ($dates as $times => $values) {

                if ($times === '00') { //00시는 전날 23시 값을 뺀다. 전날 23시 값이 없으면 0
                    
                    $before_day = Carbon::createFromFormat('Ymd', $regdates)->subDays(1)->format('Ymd');
                    
                    foreach ($data_kind as $_v) {
                        if (isset($date_data[$before_day]['23'][$_v])) {
                            $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$before_day]['23'][$_v];
                        }
                        else {
                            if ($startZeroDate === false) {
                                $date_time_table[$regdates][$times][$_v] = 0; 
                            }
                            else {
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v]; 
                            }
                        }
                    }
                    
                    /////////////////////////////////////////////////
                    //주간데이터는 당일 00시에서 전날 00시 값을 뺀다.

                    foreach ($data_kind as $_v) {
                        if (isset($date_data[$before_day]['00'][$_v])) {
                            $day_data[$regdates][$_v] = $date_data[$regdates]['00'][$_v] - $date_data[$before_day]['00'][$_v];
                        }
                        else { //전날값이 없을경우
                            if ($startZeroDate === false) { //변동량은 0
                                $day_data[$regdates][$_v] = 0;
                            }
                            else {  //첫날일 경우 첫날 데이터
                                $day_data[$regdates][$_v] = $date_data[$regdates]['00'][$_v];
                            }
                        }
                    }                    

                    //당일날짜 현재시간까지 연산하기 위해서 추가 20181207
                    if ($now_hour > 1 && $now_day == $regdates && isset($date_data[$now_day]['00'][$_v]) ) {
                        foreach ($data_kind as $_v) {
                            $day_data[$after_day][$_v] = $date_data[$now_day][$now_hour][$_v] - $date_data[$now_day]['00'][$_v];
                        }
                    }

                    $startZeroDate = false;

                    //날짜별 데이터는 00시 기준데이터로 저장.
                    $date[$regdates] = $date_data[$regdates]['00'];
                    
                }
                else if ($times == '23') { //게시물 수집 마지막날은 23시까지 크롤링이 되기때문에 이날은 00시에 하던 해당날짜의 대표값을 23시에 해야한다. 20181130
                    $before_hour = str_pad((($times*1) - 1), 2, '0', STR_PAD_LEFT);
                    foreach ($data_kind as $_v) {
                        if (isset($date_data[$regdates][$before_hour][$_v])) 
                            $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$regdates][$before_hour][$_v];
                        else {
                            if ($startDate === false) {
                                $date_time_table[$regdates][$times][$_v] = 0;
                            }
                            else {
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v];
                            }
                        }
                    }

                    $after_day = Carbon::createFromFormat('Ymd', $regdates)->addDays(1)->format('Ymd');

                    if (!isset($date_data[$after_day]['00'][$_v])) { //다음날 00시 값이 없는 이유는 1.크롤링이 끝남 2. 아직 12시가 안됨.
                        $date[$after_day] = $date_data[$regdates]['23'];
                    }
                }
                else { //01~22시까지의 값은 지금값에서 한시간전의 값을 뺀다. 
                    $before_hour = str_pad((($times*1) - 1), 2, '0', STR_PAD_LEFT);
                    foreach ($data_kind as $_v) {
                        if (isset($date_data[$regdates][$before_hour][$_v])) 
                            $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v] - $date_data[$regdates][$before_hour][$_v];
                        else {
                            if ($startDate === false) {
                                $date_time_table[$regdates][$times][$_v] = 0;
                            }
                            else {
                                $date_time_table[$regdates][$times][$_v] = $date_data[$regdates][$times][$_v];
                            }
                        }
                    }

                    //00시에 일간데이터를 연산하기때문에 당일 날짜의 데이터가 없다.(00시가 지나지 않았기 때문). 하여 가장 최근 뽑아낸 시간을 기준으로 당일 데이터를 연산 20181207
                    $now_day = Carbon::now()->format('Ymd');
                    $now_hour = Carbon::now()->format('H');
                    $after_day = Carbon::now()->addDays(1)->format('Ymd');

                    if ((int)$now_hour > 1 && $regdates == $now_day && $times == $now_hour) {
                        $date[$after_day] = $date_data[$now_day][$now_hour];
                    }
                }

                $date_time_table[$regdates][$times]['follower'] = $date_data[$regdates][$times]['follower'];
            }
            
            $startDate = true;
        }
        
        $date_time_table_total = array();
        //날짜별로 분류된 값들을 시간대별로 합산한다
        
        foreach ($date_time_table as $regdates => $dates) {
            # code...
            foreach ($dates as $times => $values) {
                foreach ($data_kind as $_v) {
                    if (isset($date_time_table[$regdates][$times][$_v]))
                        @$date_time_table_total[$times][$_v] += $date_time_table[$regdates][$times][$_v];
                }

                if (!isset($date_time_table_total[$times]['follower']) || $date_time_table_total[$times]['follower'] < $date_data[$regdates][$times]['follower'] )
                $date_time_table_total[$times]['follower'] = $date_data[$regdates][$times]['follower'];                
            }                
        }
        
        $date_value = [];
        $before_date = [];
        $date_value_cnt=0;
        ksort($date);
        foreach ($date as $key => $value) { //시간리포트 일자별 데이터. 증감률은 본래 js에서 하기때문에 엑셀에서 보여주기 위해서 한번더 가공한다.
            
            if ($date_value_cnt == 0) { //첫날의 증감률은 그날의 데이터
                $date_value[$key]['follower'] = $value['follower'];

                foreach ($data_kind as $_v) {
                    $date_value[$key][$_v] = $value[$_v];
                }

                $before_date = $date_value[$key];
            }
            else {
                $date_value[$key]['follower'] = $value['follower'];

                foreach ($data_kind as $_v) {
                    $date_value[$key][$_v] = $value[$_v] - $before_date[$_v];
                }
    
                $before_date = $value;
            }

            $date_value_cnt++;

        }

        krsort($date_value);

        //주간 요일 리포트용 데이터
        $week = array();

        foreach ($day_data as $key => $value) {
            $week_map = Carbon::createFromFormat('Ymd', $key);
            
            foreach ($data_kind as $_v) {
                @$week[$week_map->dayOfWeek][$_v] += $day_data[$key][$_v];
            }

            $week[$week_map->dayOfWeek]['follower'] = $date[$key]['follower'];
        }

        ksort($week);
       
        $channel_list = $this->get_channel_list_data($id1);

        if ($campaign_table->approve_complet_date)
            $sdate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign_table->approve_complet_date)->addDay()->format('Y-m-d');
        else 
            $sdate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign_table->sdate)->format('Y-m-d');
            
        $edate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign_table->edate)->format('Y-m-d');
        
        $send = new Request();
        $hash = $this->hashtag_view($send, $id, $id1);
        $hash = json_decode($hash->getContent());
        $hashtag_data = $hash->data->hash_data;
        

        $this->excel_download($campaign, $date_value, $week, $date_time_table_total, $channel_list, $hashtag_data);
        return  ReturnData::getData()->json_data(200);



    }

    //엑셀다운
    public function excel_download($campaign, $day, $week, $date_time_table_total, $channel_list, $hashtag_data) 
    {
        $file_name = iconv('utf-8', 'euc-kr', '인퍼블릭_'.$campaign['title'].'_결과 보고_'.date('Ymd',time()));

        \Maatwebsite\Excel\Facades\Excel::load(public_path().'/template_file/report.xls', 
                                                                function($reader) 
                                                                use ($campaign, $day, $week, $date_time_table_total, $channel_list, $hashtag_data) 
        {

            $reader->sheet('캠페인리포트', function ($sheet) use ($campaign) {

                $sheet->setCellValue('C3', $campaign['title']);
                $sheet->setCellValue('C4', $campaign['brand_name']);
                $sheet->setCellValue('C5', $campaign['content_type']);
                $sheet->setCellValue('C6', $campaign['campaign_date']);
                $sheet->setCellValue('C7', $campaign['charge_total']);
                $sheet->setCellValue('C8', $campaign['channel_quantity']);
                $sheet->setCellValue('C9', $campaign['follower'] == 0 ? '제한없음' : $campaign['follower']);

                $sheet->setCellValue('C15', $campaign['channel_count']);
                $guarantee = $campaign['boundary_follower'] > 0 ? $campaign['boundary_follower']*$campaign['channel_quantity'] : 1000*$campaign['channel_quantity'];
                $sheet->setCellValue('C16', $guarantee);
                $kpi = $campaign['follower_total'] / $guarantee;
                
                $sheet->setCellValue('C17', $kpi);
                $sheet->setCellValue('C18', $campaign['follower_total']);
                $sheet->setCellValue('C19', $campaign['engagement']);
                $sheet->setCellValue('C20', $campaign['engagement_rate']);
                $sheet->setCellValue('C21', $campaign['like']);
                $sheet->setCellValue('C22', $campaign['comment']);
                $sheet->setCellValue('C23', $campaign['organic_buzz']);
                $sheet->setCellValue('C24', $campaign['views']);

                $sheet->setCellValue('E15', $campaign['cost_per_channel']);
                $sheet->setCellValue('E18', $campaign['cost_per_follower']);
                $sheet->setCellValue('E19', $campaign['cost_per_engagement']);
                $sheet->setCellValue('E21', $campaign['cost_per_like']);
                $sheet->setCellValue('E22', $campaign['cost_per_comment']);
                $sheet->setCellValue('E23', $campaign['charge_total']/$campaign['organic_buzz']);
            });
            
            $reader->sheet('채널리포트', function ($sheet) use ($channel_list) {

                $row = 4;
                $count = 1;
                $now = Carbon::now();

                foreach ($channel_list as $key=>$val) {
                    $sheet->setCellValue('B'.$row, $count);

                    $created_time='';
                    if (isset($val->created_posting_date)) {
                        $created_time = Carbon::createFromFormat('Y-m-d H:i:s', $val->created_posting_date)->format('Y-m-d');
                        
                        $diff = $now->diffInDays($created_time);
                        if ($diff > 0) $posting_date = $now->diffInDays($created_time);
                        else $posting_date = 1;
                    }
                    else {
                        $posting_date = '';  
                    }

                    $sheet->setCellValue('C'.$row, $created_time);
                    $sheet->setCellValue('D'.$row, $val->channel_uid);
                    $sheet->setCellValue('E'.$row, $val->now_follower);
                    $sheet->setCellValue('H'.$row, $val->like);
                    $sheet->setCellValue('I'.$row, $val->comment);
                    $sheet->setCellValue('J'.$row, $val->views);
                    $sheet->setCellValue('K'.$row, $posting_date);
                    $sheet->setCellValue('L'.$row, $val->edit_count);
                    $sheet->setCellValue('M'.$row, $val->score);
                    $sheet->setCellValue('N'.$row, $val->post_url);
                    
                    $sheet->setCellValue('F'.$row, $val->like+$val->comment);
                    $sheet->setCellValue('G'.$row, (isset($val->now_follower) && $val->now_follower > 0) ? ($val->like+$val->comment)/$val->now_follower : 'follower error');

                    $row++;
                    $count++;
                }
            });

            $reader->sheet('시간리포트', function ($sheet) use ($campaign, $day) {

                $row = 4;
                foreach ($day as $key=>$val) {
                    $created_time = Carbon::createFromFormat('Ymd', $key)->subDay()->format('Y-m-d');

                    $sheet->setCellValue('B'.$row, $created_time);
                    $sheet->setCellValue('C'.$row, $val['follower']);
                    $sheet->setCellValue('F'.$row, $val['like']);
                    $sheet->setCellValue('G'.$row, $val['comment']);
                    $sheet->setCellValue('H'.$row, $val['views']);
                    $sheet->setCellValue('D'.$row, $val['like'] + $val['comment']);
                    $sheet->setCellValue('E'.$row, (isset($val['follower']) && $val['follower'] > 0) ? ($val['like'] + $val['comment'])/$val['follower'] : 'follower error');
                    $row++;
                }
            });

            $reader->sheet('시간리포트 (2)', function ($sheet) use ($week, $date_time_table_total) {
                
                $row = 4;
                for ($i=1;$i<=6;$i++) {
                    if (isset($week[$i]['follower']) && $week[$i]['follower']) {
                        $sheet->setCellValue('C'.$row, $week[$i]['follower']);
                        $sheet->setCellValue('F'.$row, $week[$i]['like']);
                        $sheet->setCellValue('G'.$row, $week[$i]['comment']);
                        $sheet->setCellValue('H'.$row, $week[$i]['views']);
                        
                        $sheet->setCellValue('D'.$row, $week[$i]['comment'] + $week[$i]['like']);
                        $sheet->setCellValue('E'.$row, ($week[$i]['comment'] + $week[$i]['like']) / $week[$i]['follower']);
                    }
                    $row++;
                }

                if (isset($week[0]['follower']) && $week[0]['follower']) {
                    $sheet->setCellValue('C10', $week[0]['follower']);
                    $sheet->setCellValue('F10', $week[0]['like']);
                    $sheet->setCellValue('G10', $week[0]['comment']);
                    $sheet->setCellValue('H10', $week[0]['views']);
                    $sheet->setCellValue('D10', $week[0]['comment'] + $week[0]['like']);
                    $sheet->setCellValue('E10', ($week[0]['comment'] + $week[0]['like']) / $week[0]['follower']);
                }

                $row = 16;
                for ($i=1;$i<=23;$i++) {
                    $num = str_pad($i, 2, "0", STR_PAD_LEFT);

                    if (isset($date_time_table_total[$num]['follower']) && $date_time_table_total[$num]['follower']) {
                        $sheet->setCellValue('C'.$row, $date_time_table_total[$num]['follower']);
                        $sheet->setCellValue('F'.$row, $date_time_table_total[$num]['like']);
                        $sheet->setCellValue('G'.$row, $date_time_table_total[$num]['comment']);
                        $sheet->setCellValue('H'.$row, $date_time_table_total[$num]['views']);
                        $sheet->setCellValue('D'.$row, $date_time_table_total[$num]['comment'] + $date_time_table_total[$num]['like']);
                        $sheet->setCellValue('E'.$row, ($date_time_table_total[$num]['comment'] + $date_time_table_total[$num]['like']) / $date_time_table_total[$num]['follower']);
                    }
                    $row++;
                }

                if (isset($date_time_table_total["00"]['follower']) && $date_time_table_total["00"]['follower']) {
                    $sheet->setCellValue('C39', $date_time_table_total["00"]['follower']);
                    $sheet->setCellValue('F39', $date_time_table_total["00"]['like']);
                    $sheet->setCellValue('G39', $date_time_table_total["00"]['comment']);
                    $sheet->setCellValue('H39', $date_time_table_total["00"]['views']);
                    $sheet->setCellValue('D39', $date_time_table_total["00"]['comment'] + $date_time_table_total["00"]['like']);
                    $sheet->setCellValue('E39', ($date_time_table_total["00"]['comment'] + $date_time_table_total["00"]['like']) / $date_time_table_total["00"]['follower']);
                }
            });

            $reader->sheet('해시태그리포트', function ($sheet) use ($hashtag_data) {
                $title_count=0;
                // $title = ['M', 'K', 'I', 'G', 'E'];
                // $title_sub = ['N', 'L', 'J', 'H', 'F'];

                $title = ['E', 'G', 'I', 'K', 'M', 'O', 'Q', 'S'];
                $title_sub = ['F', 'H', 'J', 'L', 'N', 'P', 'R', 'T'];

                foreach ($hashtag_data as $key=>$val) {
                    if ($title_count >= 8) break;
                    $sheet->setCellValue($title[$title_count].'3', '#'.$key);
                    
                    $row = 5;
                    $count = 0;
                    // $hashtag_data_sort = array_reverse($hashtag_data_sort);

                    //최초 순서대로 나오니 한번 뒤집자 20181129
                    $val->regdate = array_reverse($val->regdate); 
                    $val->tag_count = array_reverse($val->tag_count);
                    foreach ($val->regdate as $k => $v) {
                        $created_time = Carbon::createFromFormat('Y-m-d H:i:s', $v)->format('Y-m-d');
                        $sheet->setCellValue('B'.$row, $created_time);
                        $sheet->setCellValue($title[$title_count].$row, $val->tag_count[$k]);
                        $sheet->setCellValue($title_sub[$title_count].$row, "=IF(ISBLANK(".$title[$title_count].((int)$row+1)."), 0, ".$title[$title_count].$row.'-'.$title[$title_count].((int)$row+1).")" );
                        
                        $sheet->setCellValue('C'.$row, "=SUM(E$row,G$row,I$row,K$row,M$row, O$row, Q$row, S$row)");
                        $sheet->setCellValue('D'.$row, "=SUM(F$row,H$row,J$row,L$row,N$row, P$row, R$row, T$row)");

                        $row++;
                    }
                    $title_count++;
                }

            });
        })->setFilename($file_name)->download('xls');
    }

    public function get_channel_list_data($id1)
    {
        $campaign_channel=campaign_channel::where('campaign_no', $id1)->whereIn('status', ['게재', '수정요청', '검수중', '완료', '종료'])->select("channel_no")->get();

        $query = DB::table('post as T1')
                    ->leftjoin('campaign_channel as T2', function ($join) {
                        $join->on('T2.campaign_no', 'T1.campaign_no')
                            ->on('T2.channel_no', 'T1.channel_no');
                    })
                    ->leftjoin('post_log as T3', 'T3.post_log_no', 'T2.post_log_no')
                    ->select('T3.channel_uid'
                            ,DB::raw("(select follower from channel_log where channel_no=T1.channel_no and date(T3.regdate) = date(regdate) order by regdate desc limit 1) as now_follower")
                            ,'T3.like'
                            ,'T3.comment'
                            ,'T3.views'
                            ,DB::raw("(select count(*) from edit_request where T1.post_no = post_no) as edit_count")
                            ,DB::raw("(select avg(score) from post_score where campaign_channel_no=T1.campaign_channel_no) as score")
                            ,'T3.post_url'
                            ,'T1.regdate as created_posting_date'
                            )
                    ->where('T1.campaign_no',$id1)
                    ->where('T1.post_yn','사용')
                    ->where('T3.post_status', '정상')
                    ->whereIn('T2.channel_no', $campaign_channel)
                    ->orderby('created_posting_date', 'desc')
                    ->get();

        return $query->toArray();
    }

    public function excel_campaign_report($sheet)
    {

        $width = array('A'=>'2', 'B'=>20, 'C'=>20, 'D'=>15, 'E'=>15, 'F'=>15, 'G'=>15, 'H'=>15, 'I'=>15, 'J'=>10, 'K'=>10, 'L'=>20, 'M'=>15, 'N'=>15, 'O'=>15, 'P'=>15, 'Q'=>12, 'R'=>10, 'S'=>12, 'T'=>50 );

        $sheet->cell('B1', function($cell) {
            $cell->setValue('캠페인 정보');
            $cell->setVAlignment('center');
            $cell->setAlignment('left');
            $cell->setFontColor('#005cff');
            $cell->setFontSize(16);
        });

        for ($i=1;$i<=20;$i++) {
            $sheet->setHeight($i, 22.5);
        }

        $sheet->cells('B2:E2', function($cells) {$cells->setBorder(array('bottom' => array('style' => 'medium')));});
        $sheet->cells('B3:E3', function($cells) {$cells->setBorder(array('bottom' => array('style' => 'thin')));});
        $sheet->cells('B4:E4', function($cells) {$cells->setBorder(array('bottom' => array('style' => 'thin')));});
        $sheet->cells('B5:E5', function($cells) {$cells->setBorder(array('bottom' => array('style' => 'thin')));});
        $sheet->cells('B6:E6', function($cells) {$cells->setBorder(array('bottom' => array('style' => 'medium')));});

        $sheet->cells('B3:B6', function($cells) {
            $cells->setVAlignment('center');
            $cells->setAlignment('center');
            $cells->setFontSize(10);
        });

        $sheet->setCellValue('B3', '캠페인');
        $sheet->setCellValue('B4', '브랜드');
        $sheet->setCellValue('B5', '소재유형');
        $sheet->setCellValue('B6', '게시물 등록기간');

        $sheet->cell('B9', function($cell) {
            $cell->setValue('결과 요약');
            $cell->setVAlignment('center');
            $cell->setAlignment('left');
            $cell->setFontColor('#005cff');
            $cell->setFontSize(16);
        });



        $sheet->setWidth($width);

    }

    //엑셀다운 원본데이터
    public function excel_down($query) {
        $data = $query->toArray();

        $excel_title[0] = array(
            'post_log_no'=> '게시물 로그 고유번호',
            'user_id'=> '회원아이디',
            'channel_no'=> '채널 고유번호',
            'channel_uid'=> '채널 이름',
            'platform'=> '채널 플랫폼',
            'follower' => '채널 팔로워',

            'post_no'=> '게시물 고유번호',
            'post_show'=> '게시물 상태',
            'post_type'=> '게시물 유형',

            //'post_caption'=> '게시물 내용',
            'created_time'=> '게시물 생성일시',
            'regdate'=> '게시물 수집일시',
            'post_url'=> '게시물 주소',
            'like'=> '게시물 좋아요',
            'comment'=> '게시물 댓글',
            'views'=> '영상 조회수',
            'share'=> '게시물 공유(저장)',
            'post_status'=> '게시물 상태',
            'platform_url'=> '채널 주소',
            'channel_status'=> '채널상태',
            'channel_regdate'=> '채널 수집일시',
            
        );

        $excel_date = array_merge($excel_title,$data);
//          dump($excel_date);//exit;

        $data= json_decode( json_encode($excel_date), true);

        return \Maatwebsite\Excel\Facades\Excel::create('통계_'.date('Ydm',time()), function($excel) use ($data) {
            $excel->sheet('mySheet', function($sheet) use ($data)
            {
                $sheet->fromArray($data, null, 'A1', false, false)->setAutoSize(true);
            });
        })->download('xls');
    }
    

    //$id=user_id, $id1=campaign_no
    public function hashtag_view(Request $request, $id, $id1)
    {
        //캠페인의 시작일부터 캠페인 종료후 28일까지 수집한 데이터를 보여준다 2018 09 07
        //캠페인 시작후 제일 늦게 포스팅된 게시물 기준으로 28일 후까지의 데이터를 보여준다. 2018 11 16

        $campaign = campaign::find($id1);
        if ($campaign->approve_complet_date)
            $sdate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->approve_complet_date)->addDay()->format('Y-m-d');
        else 
            $sdate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->sdate)->format('Y-m-d');
            
        //각각의 캠페인내에서 제일 늦게 포스팅된 게시물의 날짜를 구한다.
        $regdate = DB::table('post')->where('campaign_no', $id1)
                                    ->where('platform', 'instagram')
                                    ->where('post_yn', '사용')
                                    ->max('regdate');

        //제일 늦게 포스팅된 게시물의 날짜를 기준으로 28일을 더한다 
        $edate = Carbon::createFromFormat('Y-m-d H:i:s', $regdate)->format('Y-m-d');

        $hashtag = DB::table('hash_tag as T1')->leftjoin('hash_tag_log as T2', 'T1.tag','=','T2.tag_name')
                                            ->select('T1.tag', 'T2.tag_count', 'T2.tag_url', 'T2.regdate')
                                            ->where('T1.campaign_no',$id1)
                                            ->where('T1.use_yn', '사용')
                                            ->whereRaw(DB::raw("date_format(T2.regdate,'%Y-%m-%d') >= '$sdate' AND date_add('$edate', interval + 28 day ) >= date_format(T2.regdate,'%Y-%m-%d')"))
                                            ->orderby('T2.regdate', 'asc')
                                            ->get();

        $hashtag_data = [];
        foreach ($hashtag as $key => $value) {
            //if (isset($hashtag_data[$value->tag]['tag_count']) && count($hashtag_data[$value->tag]['tag_count']) > 3 ) continue;

            $hashtag_data[$value->tag]['tag_count'][] = $value->tag_count;
            $hashtag_data[$value->tag]['regdate'][] = $value->regdate;
            $hashtag_data[$value->tag]['tag_url'] = $value->tag_url;
            $hashtag_data[$value->tag]['tag_count_last'] = $value->tag_count;
            $hashtag_data[$value->tag]['regdate_last'] = $value->regdate;

        }

        //캠페인에 등록된 해시태그 순서대로 가져오기 위해서 다시한번 캠페인 쿼리
        $hashtag_sort = DB::table('campaign as T1')
                        ->select('T1.guide_tag')
                        ->where('T1.campaign_no',$id1)
                        ->first();

        $tmp = str_replace(',', '', $hashtag_sort->guide_tag);
        $tmp = str_replace('#', '', $tmp);
        $tmp = str_replace('  ', ' ', $tmp);

        // $tmp = preg_replace('/\,|\.|\r\n|\r|\n|\#/', '', $hashtag_sort->guide_tag);
        // $tmp = preg_replace('/\s{2,}/', ' ', $tmp);
        
        $tags = explode(' ', $tmp);
        
        $hash_count = count($hashtag_data);
        if ($hash_count > 8) $hash_count = 8;
        
        $hashtag_data_sort = [];
        for ($i=0;$i<$hash_count;$i++) {
            $hashtag_data_sort[$tags[$i]] = isset($hashtag_data[$tags[$i]]) ? $hashtag_data[$tags[$i]] : 0;
        }

        $shortcode = DB::table('post')->select('post_id')
                        ->where('campaign_no', $id1)
                        ->where('post_yn', '사용')
                        ->where('sc_post_date', null)
                        ->get();
                        
        return  ReturnData::getData(['hash_data'=> $hashtag_data_sort, 'shortcode_data'=>$shortcode])->json_data('200');

    }
}

