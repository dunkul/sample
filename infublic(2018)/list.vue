<template>
<div >
    <div class="row row-cards">
        <div class="col-sm-6 col-lg-2" v-for="(status, index) in campaign_status" v-bind:key="index">
            <div class="card p-3">
                <div class="d-flex align-items-center">
                    <span :class="'stamp stamp-md mr-3 bg-' + campaign_status_color[index] ">
                        {{ status }}&nbsp;
                    </span>
                    <div>
                        <h4 class="m-0"><a :href="'#campaign_' + index" class="sliding-link">{{ campaign_count[status] }} <small></small></a></h4>
                        <small class="text-muted">Campaigns</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card p-3">
                <div class="d-flex align-items-center">
                    <span class="stamp stamp-md mr-3 bg-orange">
                        테스트&nbsp;
                    </span>
                    <div>
                        <h4 class="m-0"><a href="javascript:">{{ campaign_count['비공개'] }} <small></small></a></h4>
                        <small class="text-muted">Campaigns</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card col-12 card-collapsed" >
        <div class="card-header " data-toggle="card-collapse" style="cursor:pointer;">
            <h3 class="card-title">Search Options</h3>
                            <div class="card-options">
                <a href="#" class="card-options-collapse" ><i class="fe fe-chevron-up"></i></a>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-6 col-lg-4">
                    <div class="form-group">
                        <label class="custom-switch">
                            <input type="checkbox" v-model="filter_except_test_campaign" class="custom-switch-input">
                            <span class="custom-switch-indicator"></span>
                            <span class="custom-switch-description">테스트 캠페인 제외</span>
                        </label>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="form-group">
                    <div class="custom-controls-stacked">
                        <label class="custom-control custom-radio custom-control-inline">
                        <input type="radio" class="custom-control-input" v-model="filter_content_type" value="전체" checked>
                        <span class="custom-control-label">전체</span>
                        </label>
                        <label class="custom-control custom-radio custom-control-inline">
                        <input type="radio" class="custom-control-input" v-model="filter_content_type" value="이미지">
                        <span class="custom-control-label">이미지</span>
                        </label>
                        <label class="custom-control custom-radio custom-control-inline">
                        <input type="radio" class="custom-control-input" v-model="filter_content_type" value="비디오">
                        <span class="custom-control-label">비디오</span>
                        </label>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-for="(status, index) in campaign_status" v-bind:key="index">

        <div class="card" :id="'campaign_' + index">
            <div :class="'card-status bg-' + campaign_status_color[index]"></div>
            <div class="card-header" data-toggle="card-collapse" style="cursor:pointer;">
                <h3 class="card-title">{{ status }} 캠페인</h3>
                <div class="card-options">
                    <a href="#" class="card-options-collapse" ><i class="fe fe-chevron-up"></i></a>
                </div>
            </div>
            <div class="card-body">
                <div>
                    <div v-if="campaign_list[status]" class="row row-cards">
                        <div class="col-sm-6 col-lg-3"  v-for="(campaign, index) in filterCampaignList(status)" v-bind:key="index"  @mouseover="hovered = status + index" @mouseleave="hovered = null">
                            <div class="card p-3">

                                <a href="javascript:void(0)" @click="popupCampaignDetail(campaign, index, status)" class="mb-3">

                                        <transition name="fade">
                                    
                                        <div  v-if="hovered === status + index" class="image_hover">
                                            <p >{{ campaign.content_type}}<br>게시물 / 모집 <br> {{ campaign.campaign_post_count }} / {{ campaign.channel_quantity }} </p>
                                        </div>
                                        
                                        </transition>
                                    <img :src="'https://www.infublic.com/campaign_file/' + (campaign.content_img_0 != null ? campaign.content_img_0 : 'no_image.jpg')" alt="" width=100% class="rounded" @mouseover="hovered = status + index" @mouseleave="hovered = null">

                                    <div class="campaign_status_detail" style="float:right;right:15px;">{{ campaign.follower == 0 ? '제한없음' : campaign.follower.toLocaleString() + '명+'}}</div>

                                    <div class="campaign_test" v-if="campaign.campaign_type == '비공개'"><div class="campaign_test_text">테스트</div></div>

                                    <div class="campaign_status_detail" style="left:15px;" v-if="campaign_status.indexOf(campaign.status) == -1"><p>{{ campaign.status }}</p></div>
                                </a>

                                <div class="d-flex align-items-center px-2">
                                    <div>
                                        <div> {{ campaign.title }} </div>
                                        <small class="d-block text-muted">{{ setScheduleDate(campaign.sdate, campaign.edate) }}</small>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <div v-if="campaign_count[status] == 0" class="campaign_empty">
                        <p>{{ status }} 중인 캠페인이 없습니다</p>
                    </div>
                </div>
            </div>
        
        </div>
    </div>



    <a href="javascript:" id="top_btn" style="position: fixed;top: 90%;right: 20px;background: #fff;width: 50px;height: 50px;text-indent: -9999px;background: url(/assets/img/top_btn.gif) no-repeat;overflow: hidden;opacity: 0.8;">top</a>

    <modals-container  />
</div>

</template>

<script>
import campaignDetailPopup from './detail/index.vue';

    export default {
        components: {
            
        },
        data() {
            return {
                campaign_list: [],
                campaign_status_list: [],
                campaign_status: ['신청', '대기', '진행', '종료', '취소'],
                campaign_count: {'신청':0, '진행':0, '대기':0, '종료':0, '취소':0, '비공개':0},
                campaign_status_color: ['green', 'blue', 'yellow', 'purple', 'red'],
                campaign_popup_index: '',
                campaign_popup_status: '',
                click_campaign_no : 0,
                isPopup: false,
                windowWidth: '',
                hovered: null,

                filter_except_test_campaign : true,
                filter_content_type : '전체',
            }
        },
        created() {
            this.getCampaignList();
            this.windowWidth = window.innerWidth;
            
            this.$eventHub.$on('campaign_data', (campaign => {
                this.campaign_list[this.campaign_popup_status][this.campaign_popup_index] = campaign;
            }));
                
            this.$eventHub.$on('campaign_data_refresh', ( () => {
                this.getCampaignList();
            }));

        },
        mounted() {
            $('#top_btn').fadeOut();  //차후에 트랜지션으로 교체
        },
        watch: {
            filter_except_test_campaign: function() {
                this.checkCampaignCount();
            },
            filter_content_type: function () {
                this.checkCampaignCount();
            }
        },
        methods: {
            setScheduleDate(sdate, edate) {
                return Vue.getDateFormat(sdate, 'YYYY-MM-DD') + ' ~ ' + Vue.getDateFormat(edate, 'YYYY-MM-DD');
            },
            filterCampaignList(status) {
                return this.campaign_list[status].filter(i => ( (this.filter_except_test_campaign == false || i.campaign_type === '공개') && (this.filter_content_type == '전체' || (this.filter_content_type !== '전체' && i.content_type == this.filter_content_type)) )   );
            },

            getCampaignList() {
                let url = "/api/campaign";
                Vue.send_ajax('get', url).then(res => {
                    this.campaign_list = res.data.data.campaign;
                    this.$store.state.price_policy = res.data.data.price_policy;
                    this.$store.state.campaign_status_list = res.data.data.status;

                    this.checkCampaignCount();
                }, error => {
                    location.href='/';
                });
            },

            checkCampaignCount() {
                //object 전체key의 value값 초기화 
                this.campaign_count = Object.keys(this.campaign_count).reduce((acc, key) => {acc[key] = 0; return acc; }, {})

                for (var key in this.campaign_list) {
                    this.campaign_list[key].forEach(element => {

                        if (this.filter_except_test_campaign == true) { //비공개 제외, 공개만
                            if (element.campaign_type == '공개') {

                                if (this.filter_content_type !== '전체') {
                                    if (this.filter_content_type == element.content_type) {
                                        this.campaign_count[key]++;
                                    }
                                }
                                else {
                                    this.campaign_count[key]++;
                                }
                            }
                        }
                        else { //전체
                            if (this.filter_content_type !== '전체') {
                                if (this.filter_content_type == element.content_type) {
                                    this.campaign_count[key]++;
                                }
                            }
                            else {
                                this.campaign_count[key]++;
                            }
                        }

                        if (element.campaign_type == '비공개') {
                            this.campaign_count['비공개']++;
                        }
                    });
                }

            },
            popupCampaignDetail(campaign, index, status) {
                this.$modal.show(campaignDetailPopup,{
                    campaign : campaign,
                    modal : this.$modal },{
                        name: 'dynamic-modal',
                        width : (this.windowWidth < 1000 ? '100%' : '1184px'),
                        height : 'auto',
                        draggable: false,
                        scrollable: true,
                });

                this.campaign_popup_index = index;
                this.campaign_popup_status = status;
            }
        }
    }
</script>


<style>
    .campaign_empty {
        width: 100%;
    }
    .campaign_empty p {
        text-align: center;
    }

    .campaign_status_detail {
        position: relative;
        float: left;
        border: 1px solid #ffffff;
        width: 75px;
        top: -33px;
        background-color: rgba(0, 0, 0, 0.3);
        font-size: 13px;
        font-weight: 400;
        text-align: center;
        color: #ffffff;
    }

    .campaign_status_detail p {
        color: #ffffff;
        font-weight: 400;
        margin-bottom: 0;
    }

    .campaign_test {
        position: absolute; 
        border-width: 25px; 
        border-style: solid; 
        border-color: rgb(213, 7, 15) transparent transparent; 
        border-image: initial; 
        bottom: 20px; 
        padding: 0px 10px; 
        width: 120px; 
        color: white; 
        top: 12px; 
        left: 12px;
    }
    .campaign_test_text {
        position: absolute; 
        top: -24px; 
        left: 13px; 
        font-size: 15px;
    }

    /*---------- Quick Menu ----------*/
/* .quick-menu {position:fixed;z-index:1;left:92%;top:20%;width:68px;border:1px solid #dadada;background:#fff;border-radius:2px !important;}
.quick-menu.quick-menu-main {top:25px}
.quick-menu .quick-menu-box {position:relative;display:block;text-align:center;padding:10px 0;border-bottom:1px solid #dadada;line-height: 1.42857143;}
.quick-menu .quick-menu-box.first-box {background:#2E3340;margin:-1px;border-bottom:1px solid #2E3340;line-height: 1.42857143;}
.quick-menu .quick-menu-box.heading-current {padding:3px 0;background:#f5f5f5}
.quick-menu .quick-menu-box.current-view {padding:5px;border-bottom:0}
.quick-menu .quick-menu-box a {text-decoration: none;}
.quick-menu .quick-menu-box a i {color:#757575;font-size:18px}
.quick-menu .quick-menu-box span {display:block;color:#2E3340;font-size:11px;padding-top:2px;letter-spacing:-1px}
.quick-menu .quick-menu-box a:hover i, .quick-menu ul li a:hover span {color:#000}
.quick-menu .quick-menu-box.first-box a i {color:#ced1d8}
.quick-menu .quick-menu-box.first-box span {color:#ced1d8}
.quick-menu .quick-menu-box.first-box a:hover i {color:#fff}
.quick-menu .quick-menu-box .quick-carousel {width:100%}
.quick-menu .quick-menu-box .item-image img {width:100%;height:auto;background:#fff;margin-bottom:5px}
.quick-menu .quick-menu-box p {width:100%;height:80px;padding-top:20px;letter-spacing:-1px;font-size:11px;background:#fff;margin:0;color:#959595}
.quick-menu .quick-menu-box .quick-carousel .carousel-arrow a {font-size:11px;padding-top:6px}
.quick-menu .quick-menu-box .quick-carousel .carousel-arrow a:hover, .quick-menu .quick-menu-box .quick-carousel .carousel-arrow a:focus {color:#DE2600}
.quick-menu .quick-scroll-btn {background:#000;text-align:center;color:#d5d5d5;padding:8px 0;margin:-1px;cursor:pointer}
.quick-menu .quick-scroll-btn.top-btn {padding:7px 0 9px}
.quick-menu .quick-scroll-btn.down-btn {border-top:1px solid #656565;border-radius:0 0 2px 2px !important}
.quick-menu .quick-scroll-btn i {display:block;font-size:12px;line-height:1}
.quick-menu .quick-scroll-btn span {display:block;font-size:10px;line-height:1;color:#959595} */

.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}

.fade-enter, .fade-leave-to {
  opacity: 0;
}
.image_hover {
    position: absolute;
    display: flex;
    height: 237px;
    width: 237px;
    background: rgba(0, 0, 0, 0.7);
}
.image_hover p {
    position:relative;
    text-align:center;
    color:#fff;
    width:100%;
    top:40%;
}

.campaign_follower_standard {
    position: relative;
    float: left;
    border: 1px solid #ffffff;
    width: 75px;
    left: 15px;
    top: -33px;
    background-color: rgba(0, 0, 0, 0.3);
    font-size: 13px;
    font-weight: 400;
    text-align: center;
    color: #ffffff;
}
</style>