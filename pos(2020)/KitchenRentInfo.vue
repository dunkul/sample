<template>
  <div id="kitchen_wrap">
    <div class="mt-5 mb-4">
      <h3 class="m-0 font-sm-18">
        <b>주방 사용료 안내 (VAT 별도)</b>
      </h3>
    </div>

    <div class="d-flex justify-content-between" style="align-items: flex-end;">
      <div class="px-2">
        <label class="text-muted font-sm-14">지점선택</label>
        <select
          id="store_location_id"
          class="form-control"
          style="width: 200px;"
          v-model="kitchen_data.store_location.id"
        >
          <option v-for="(item, i) in region" :key="i" :value="item.id">{{ item.name }}</option>
        </select>
        <span></span>
      </div>
      <p class="m-0 font-sm-14">단위 : 원</p>
    </div>

    <div class="kitchen_wrap card w-100 mt-3">
      <table class="table table-bordered m-0">
        <thead>
          <tr>
            <th style="width: 20%;">구 분</th>
            <th style="width:120px;">금 액</th>
            <th style="width:77px;">체크</th>
            <th style="min-width:100px;">내 용</th>
          </tr>
        </thead>
        <tbody>
          <div
            :id="'block_type_' + i"
            style="display: contents"
            v-for="(item, i) in region[kitchen_data.store_location.id].type.type"
            :key="i"
          >
            <tr>
              <td colspan="4" class="text-center bg-lightgray" style="width:90px;">
                <strong>{{ i }} 타입</strong>
              </td>
            </tr>
            <tr class="enable_type_color">
              <td class="text-center" style="min-width:80px;">보증금</td>
              <td
                class="align-middle"
                style="text-align:right;"
              >{{ parseInt(item.deposit).toLocaleString() }}원</td>
              <td rowspan="2" class="text-center">
                <input
                  :value="i"
                  @click="checked_color(i)"
                  v-model="kitchen_data.type"
                  type="radio"
                  :id="'type_' + i"
                />
                <label :for="'type_' + i"></label>
              </td>
              <td style="text-align:left;" v-if="item.deposit_text" v-html="item.deposit_text"></td>
              <td style="text-align:left;" v-else>계약시 입금</td>
            </tr>
            <tr>
              <td class="text-center">월 사용료</td>
              <td class="align-middle text-right">{{ parseInt(item.month).toLocaleString() }}원</td>
              <td class="align-middle" v-if="!item.month_text">
                기본 주방 및 공용 공간 제공
                <br />
                <span class="text-reject">
                  <img src="/images/error-d-24.png" class="align-bottom" /> 5kw 이상 전기승압 및 가스
                </span>
                <br />
                <span class="text-reject">설치비(안전검사비포함)는 별도</span>
              </td>
              <td class="align-middle" v-else v-html="item.month_text"></td>
            </tr>
          </div>

          <div id="block_essential_work" style="display: contents">
            <tr>
              <td colspan="4" class="text-center bg-lightgray">
                <strong>필수사항</strong>
              </td>
            </tr>
            <tr>
              <td class="text-center">공용 관리비</td>
              <td
                class="text-right"
              >{{ parseInt(region[kitchen_data.store_location.id].type.essential_work).toLocaleString() }}원</td>
              <td class="text-center">
                <input
                  type="checkbox"
                  v-model="kitchen_data.essential_work"
                  id="essential_work"
                  disabled
                />
                <label for="essential_work"></label>
              </td>
              <td v-if="!region[kitchen_data.store_location.id].type.essential_work_text">
                공용전기료, 공용청소비 및 공용 유지비(인터넷, 정수기, CCTV, 화재보험 등)
                <br />
                <span class="text-reject">
                  <img src="/images/error-d-24.png" class="align-bottom" /> 개별 수도/가스/광열비 별도
                </span>
              </td>
              <td v-else v-html="region[kitchen_data.store_location.id].type.essential_work_text"></td>
            </tr>
          </div>

          <!-- 월곡점 삭제 -->
          <div id="block_add_work" style="display: contents" v-if="parseInt(region[kitchen_data.store_location.id].type.add_work) > 0">
            <tr>
              <td colspan="4" class="text-center bg-lightgray">
                <strong>선택사항</strong>
              </td>
            </tr>
            <tr>
              <td class="text-center">추가 설비</td>
              <td
                class="text-right"
              >{{ parseInt(region[kitchen_data.store_location.id].type.add_work).toLocaleString() }}원</td>
              <td class="text-center">
                <input type="checkbox" v-model="kitchen_data.add_work" id="kitchen_add_work" />
                <label for="kitchen_add_work"></label>
              </td>
              <td v-if="!region[kitchen_data.store_location.id].type.add_work_text">개별 냉장/ 냉동 공간 (설비포함)</td>
              <td v-else v-html="region[kitchen_data.store_location.id].type.add_work_text"></td>
            </tr>
          </div>
          <div id="block_essential_work" style="display: contents">
            <tr style="border-top: 2px solid #dee2e6 !important">
            <td class="text-center font-weight-bold">총금액</td>
            <td class="text-right text-primary font-weight-bold">{{ calc_price }}원</td>
            <td></td>
            <td class="text-right"></td>
            </tr>
          </div>
        </tbody>
      </table>
    </div>
    <main-provide-service :special="kitchen_data.special"></main-provide-service>

    <action-btn :nextUrl="'contract'" :page="'kitchen'" :code="code" :data="kitchen_data"></action-btn>
  </div>
</template>

<script>
import { EventBus } from "./../../vue.js";

export default {
  props: ["region", "dbData"],

  data() {
    return {
      kitchen_data: {
        special: "",
        type: null,
        add_work: false,
        essential_work: false,
        total_price: 0,
        store_location: {
          id: 1,
          name: "",
          address: ""
        }
      },
      code: ""
    };
  },
  created() {
    if (this.dbData) {
      this.kitchen_data = JSON.parse(this.dbData.kitchen);
      this.code = this.dbData.code;
    }

    for (let prop in this.region) {
      this.region[prop].company_info = JSON.parse(
        this.region[prop].company_info
      );
      this.region[prop].type = JSON.parse(this.region[prop].type);
    }

    EventBus.$on("kitchenRentSpecialInfo", res => {
      this.kitchen_data.special = res;
    });
  },
  mounted() {
    this.kitchen_data.essential_work = true;

    this.checked_color(this.kitchen_data.type);
  },
  watch: {
    "kitchen_data.essential_work": function(res) {

      if (res == true) {
        $("#block_essential_work tr:nth-child(2)").addClass("bg-sky");
      } else {
        $("#block_essential_work tr:nth-child(2)").removeClass("bg-sky");
      }
    },
    "kitchen_data.add_work": function(res) {
      if (res == true) {
        $("#block_add_work tr:nth-child(2)").addClass("bg-sky");
      } else {
        $("#block_add_work tr:nth-child(2)").removeClass("bg-sky");
      }
    },
    "kitchen_data.store_location.id": function(res) {
      this.checked_color(this.kitchen_data.type);
    }
  },
  computed: {
    calc_price: function() {
      let add_work_price = 0;
      let essential_work_price = 0;
      let type_price = 0;

      if (this.kitchen_data.add_work == true) {
        add_work_price = parseInt(
          this.region[this.kitchen_data.store_location.id].type.add_work
        );
      } else {
        add_work_price = 0;
      }

      if (this.kitchen_data.essential_work == true) {
        essential_work_price = parseInt(
          this.region[this.kitchen_data.store_location.id].type.essential_work
        );
      } else {
        essential_work_price = 0;
      }

      if (this.kitchen_data.type) {
        type_price = parseInt(
          this.region[this.kitchen_data.store_location.id].type.type[
            this.kitchen_data.type
          ].month
        );
      }

      this.kitchen_data.total_price =
        type_price + add_work_price + essential_work_price;

      EventBus.$emit("kitchenRentInfo", this.kitchen_data);
      EventBus.$emit("KitchenRentInfoForContract", this.kitchen_data);
      EventBus.$emit("CmsRent", this.kitchen_data);

      return this.kitchen_data.total_price.toLocaleString();
    }
  },
  methods: {
    checked_color: function(type) {
      $(".enable_type_color").removeClass("bg-sky");
      $(".enable_type_color")
        .next()
        .removeClass("bg-sky");

      $("#block_type_" + type + " tr:nth-child(2)").addClass("bg-sky");
      $("#block_type_" + type + " tr:nth-child(3)").addClass("bg-sky");
    }
  }
};
</script>
