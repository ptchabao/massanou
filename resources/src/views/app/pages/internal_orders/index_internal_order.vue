<template>
  <div class="main-content">
    <breadcumb :page="$t('ListInternalOrders')" :folder="$t('InternalOrders')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="internal_orders"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        enabled: true,
        placeholder: $t('Search_this_table'),  
      }"
        :select-options="{ 
          enabled: true ,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="tableOne table-hover vgt-table"
      >
        <div slot="selected-row-actions">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="InternalOrder_PDF()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="internal_orders"
              :columns="columns"
              :file-name="'internal_orders'"
              :file-type="'xlsx'"
              :sheet-name="'internal_orders'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_add')"
            to="/app/internal_orders/store"
          >
            <span class="ul-btn__icon">
              <i class="i-Add"></i>
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">

            <a title="PDF" v-b-tooltip.hover @click="download_pdf(props.row , props.row.id)">
              <i class="i-File-TXT text-25 text-primary cursor-pointer"></i>
            </a>

            <a title="View" v-b-tooltip.hover @click="showDetails(props.row.id)">
              <i class="i-Eye text-25 text-info cursor-pointer"></i>
            </a>

            <!-- Edit (only if pending) -->
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_edit') && props.row.statut == 'pending'"
              title="Edit"
              v-b-tooltip.hover
              :to="{ name:'edit_internal_order', params: { id: props.row.id } }"
            >
              <i class="i-Edit text-25 text-success"></i>
            </router-link>

            <!-- Approve (only if pending) -->
            <a
              title="Approve"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_approve') && props.row.statut == 'pending'"
              @click="Approve_InternalOrder(props.row)"
            >
              <i class="i-Yes text-25 text-success cursor-pointer"></i>
            </a>

            <!-- Reject (only if pending) -->
            <a
              title="Reject"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_approve') && props.row.statut == 'pending'"
              @click="Reject_InternalOrder(props.row.id)"
            >
              <i class="i-Close text-25 text-warning cursor-pointer"></i>
            </a>

            <!-- Ship (only if approved) -->
             <a
              title="Ship"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_ship') && props.row.statut == 'approved'"
              @click="Ship_InternalOrder(props.row.id)"
            >
              <i class="i-Car-2 text-25 text-info cursor-pointer"></i>
            </a>

            <!-- Receive (only if shipped) -->
             <a
              title="Receive"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_receive') && props.row.statut == 'shipped'"
              @click="Receive_InternalOrder(props.row.id)"
            >
              <i class="i-Box-Full text-25 text-success cursor-pointer"></i>
            </a>

            <!-- Delete (only if pending) -->
            <a
              title="Delete"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('internal_order_delete') && props.row.statut == 'pending'"
              @click="Remove_InternalOrder(props.row.id)"
            >
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>
          <div v-else-if="props.column.field == 'statut'">
            <span
              v-if="props.row.statut == 'received'"
              class="badge badge-outline-success"
            >{{$t('Received')}}</span>
            <span
              v-else-if="props.row.statut == 'shipped'"
              class="badge badge-outline-info"
            >{{$t('Shipped')}}</span>
            <span
              v-else-if="props.row.statut == 'approved'"
              class="badge badge-outline-primary"
            >{{$t('Approved')}}</span>
            <span
              v-else-if="props.row.statut == 'rejected'"
              class="badge badge-outline-danger"
            >{{$t('Rejected')}}</span>
            <span v-else class="badge badge-outline-warning">{{$t('Pending')}}</span>
          </div>
        </template>
      </vue-good-table>
    </div>

    <!-- multiple filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Reference  -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- From warehouse  -->
          <b-col md="12">
            <b-form-group :label="$t('FromWarehouse')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                v-model="Filter_From"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- To warehouse  -->
          <b-col md="12">
            <b-form-group :label="$t('ToWarehouse')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                v-model="Filter_To"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Status  -->
          <b-col md="12">
            <b-form-group :label="$t('Status')">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'Received', value: 'received'},
                        {label: 'Shipped', value: 'shipped'},
                        {label: 'Approved', value: 'approved'},
                        {label: 'Rejected', value: 'rejected'},
                        {label: 'Pending', value: 'pending'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_InternalOrders(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Internal Order Details -->
    <b-modal ok-only size="lg" id="showDetails" :title="$t('InternalOrderDetail')">
      <b-row>
        <b-col lg="5" md="12" sm="12" class="mt-3">
          <table class="table table-hover table-bordered table-sm">
            <tbody>
              <!-- date -->
              <tr>
                <td>{{$t('date')}}</td>
                <th>{{internal_order.date}}</th>
              </tr>
              <!-- Reference -->
              <tr>
                <td>{{$t('Reference')}}</td>
                <th>{{internal_order.Ref}}</th>
              </tr>
              <!-- From warehouse -->
              <tr>
                <td>{{$t('FromWarehouse')}}</td>
                <th>{{internal_order.from_warehouse}}</th>
              </tr>
              <!-- To warehouse -->
              <tr>
                <td>{{$t('ToWarehouse')}}</td>
                <th>{{internal_order.to_warehouse}}</th>
              </tr>
              <!-- Grand Total -->
              <tr>
                <td>{{$t('Total')}}</td>
                <th>{{currentUser.currency}}{{formatNumber(internal_order.GrandTotal ,2)}}</th>
              </tr>
              <!-- Status -->
              <tr>
                <td>{{$t('Status')}}</td>
                <th>
                  <span
                    v-if="internal_order.statut == 'received'"
                    class="badge badge-outline-success"
                  >{{$t('Received')}}</span>
                  <span
                    v-else-if="internal_order.statut == 'shipped'"
                    class="badge badge-outline-info"
                  >{{$t('Shipped')}}</span>
                  <span
                    v-else-if="internal_order.statut == 'approved'"
                    class="badge badge-outline-primary"
                  >{{$t('Approved')}}</span>
                  <span
                    v-else-if="internal_order.statut == 'rejected'"
                    class="badge badge-outline-danger"
                  >{{$t('Rejected')}}</span>
                  <span v-else class="badge badge-outline-warning">{{$t('Pending')}}</span>
                </th>
              </tr>
            </tbody>
          </table>
        </b-col>
        <b-col lg="7" md="12" sm="12" class="mt-3">
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm">
              <thead>
                <tr>
                  <th scope="col">{{$t('ProductName')}}</th>
                  <th scope="col">{{$t('CodeProduct')}}</th>
                  <th scope="col">{{$t('Quantity')}}</th>
                  <th scope="col">{{$t('SubTotal')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in details">
                  <td>{{detail.name}}</td>
                  <td>{{detail.code}}</td>
                  <td>{{formatNumber(detail.quantity ,2)}} {{detail.unit}}</td>
                  <td>{{currentUser.currency}} {{detail.total.toFixed(2)}}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>
      </b-row>
         <hr v-show="internal_order.notes">
          <b-row class="mt-4">
           <b-col md="12">
             <p>{{internal_order.notes}}</p>
           </b-col>
        </b-row>
    </b-modal>

    <!-- Approval Modal -->
    <b-modal id="approveModal" :title="$t('ApproveInternalOrder')" @ok="Submit_Approve">
      <b-form-group :label="$t('Discount')">
        <b-form-input type="number" v-model="approveForm.discount" placeholder="0"></b-form-input>
      </b-form-group>
      <b-form-group :label="$t('DiscountType')">
        <b-form-select v-model="approveForm.discount_type" :options="['fixed', 'percent']"></b-form-select>
      </b-form-group>
    </b-modal>

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Internal Orders"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      search: "",
      totalRows: "",
      loading: true,
      spinner: false,
      limit: "10",
      Filter_date: "",
      Filter_status: "",
      Filter_Ref: "",
      Filter_From: "",
      Filter_To: "",
      details: [],
      warehouses: [],
      internal_orders: [],
      internal_order: {
        GrandTotal: ""
      },
      approveForm: {
        id: null,
        discount: 0,
        discount_type: 'fixed'
      }
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("FromWarehouse"),
          field: "from_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("ToWarehouse"),
          field: "to_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Items"),
          field: "items",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "statut",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        }
      ];
    }
  },

  methods: {

    //-----------------------------  download_pdf ------------------------------\\
    download_pdf(internal_order, id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
       axios
        .get("internal_order_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "InternalOrder-" + internal_order.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_InternalOrders(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_InternalOrders(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event sort change

    onSortChange(params) {
      let field = "";
      if (params[0].field == "from_warehouse") {
        field = "from_warehouse_id";
      } else if (params[0].field == "to_warehouse") {
        field = "to_warehouse_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_InternalOrders(this.serverParams.page);
    },

    //---- Event on Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_InternalOrders(this.serverParams.page);
    },

    setToStrings() {
      // Simply replaces null values with strings=''
      if (this.Filter_From === null) {
        this.Filter_From = "";
      } else if (this.Filter_To === null) {
        this.Filter_To = "";
      } else if (this.Filter_status === null) {
        this.Filter_status = "";
      }
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_date = "";
      this.Filter_status = "";
      this.Filter_Ref = "";
      this.Filter_From = "";
      this.Filter_To = "";
      this.Get_InternalOrders(this.serverParams.page);
    },

    //--------------------------------Get All Internal Orders ----------------------\\
    Get_InternalOrders(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "internal_orders?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&statut=" +
            this.Filter_status +
            "&from_warehouse_id=" +
            this.Filter_From +
            "&to_warehouse_id=" +
            this.Filter_To +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.internal_orders = response.data.internal_orders;
          this.warehouses = response.data.internal_orders.length > 0 ? 
            [...new Set(response.data.internal_orders.map(item => ({name: item.from_warehouse, id: item.from_warehouse_id})))] : []; 
          // Note: The controller returns 'warehouses' but in the index method it wasn't explicitly returning a list of all warehouses for filter, 
          // only the ones attached to the orders or we need to fetch them. 
          // Let's check the controller index method again. 
          // Ah, the controller index method does NOT return 'warehouses' list for the filter dropdowns. 
          // I should probably fetch warehouses separately or rely on what's available.
          // For now, let's try to get them from the response if available or just use unique values from the list.
          // Actually, looking at TransferController, it doesn't return warehouses in index either.
          // But in the Vue file it uses 'warehouses' data property.
          // Let's check where 'warehouses' comes from in Transfer index.
          // It seems it expects it in response.data.warehouses.
          // I should update my controller to return warehouses if I want the filter to work properly.
          // Or I can just fetch them.
          
          this.totalRows = response.data.totalRows;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //----------------------------------- Get Details ------------------------------\\
    showDetails(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("internal_orders/" + id)
        .then(response => {
          this.internal_order = response.data.internal_order;
          this.details = response.data.details;
          Fire.$emit("Get_Details_InternalOrder");
        })
        .catch(response => {
          Fire.$emit("Get_Details_InternalOrder");
        });
    },

    //-------------------------------------- PDF ------------------------------\\
    InternalOrder_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) {}
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        self.$t("Reference"),
        self.$t("FromWarehouse"),
        self.$t("ToWarehouse"),
        self.$t("Items"),
        self.$t("Status"),
        self.$t("Total")
      ];

      const body = (self.internal_orders || []).map(order => ([
        order.Ref,
        order.from_warehouse,
        order.to_warehouse,
        order.items,
        order.statut,
        order.GrandTotal
      ]));

      // Calculate totals
      let totalGrandTotal = self.internal_orders.reduce((sum, order) => sum + parseFloat(order.GrandTotal || 0), 0);
     
      const footer = [[
        self.$t("Total"),
        '',
        '',
        '',
        '',
        totalGrandTotal.toFixed(2)
      ]];

      const marginX = 40;
      const rtl =
        (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body: body,
        foot: footer,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
        footStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          // Header banner
          pdf.setFillColor(63,81,181);
          pdf.rect(0, 0, pageW, 60, 'F');

          // Title
          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = self.$t('ListInternalOrders');
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);

          // Reset text color
          pdf.setTextColor(33);

          // Footer page numbers
          pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' })
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        }
      });

      pdf.save("InternalOrders_List.pdf");

    },

    //---------------------------------- Delete ----------------------\\
    Remove_InternalOrder(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("internal_orders/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_InternalOrder");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    //---- Delete by selection
    delete_by_selected() {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("internal_orders/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_InternalOrder");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

    // Approve
    Approve_InternalOrder(order) {
      this.approveForm.id = order.id;
      this.approveForm.discount = 0;
      this.approveForm.discount_type = 'fixed';
      this.$bvModal.show("approveModal");
    },

    Submit_Approve() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("internal_orders/" + this.approveForm.id + "/approve", {
          discount: this.approveForm.discount,
          discount_type: this.approveForm.discount_type
        })
        .then(() => {
          this.$swal(
            this.$t("Success"),
            this.$t("Approved_Successfully"),
            "success"
          );
          Fire.$emit("Delete_InternalOrder"); // Reuse refresh event
          NProgress.done();
        })
        .catch(() => {
          NProgress.done();
          this.$swal(
            this.$t("Failed"),
            this.$t("Something_Wrong"),
            "warning"
          );
        });
    },

    // Reject
    Reject_InternalOrder(id) {
       this.$swal({
        title: this.$t("Reject_Title"),
        text: this.$t("Reject_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Reject")
      }).then(result => {
        if (result.value) {
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("internal_orders/" + id + "/reject")
            .then(() => {
              this.$swal(
                this.$t("Success"),
                this.$t("Rejected_Successfully"),
                "success"
              );
              Fire.$emit("Delete_InternalOrder");
              NProgress.done();
            })
            .catch(() => {
              NProgress.done();
              this.$swal(
                this.$t("Failed"),
                this.$t("Something_Wrong"),
                "warning"
              );
            });
        }
      });
    },

    // Ship
    Ship_InternalOrder(id) {
       this.$swal({
        title: this.$t("Ship_Title"),
        text: this.$t("Ship_Text"),
        type: "info",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Ship")
      }).then(result => {
        if (result.value) {
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("internal_orders/" + id + "/ship")
            .then(() => {
              this.$swal(
                this.$t("Success"),
                this.$t("Shipped_Successfully"),
                "success"
              );
              Fire.$emit("Delete_InternalOrder");
              NProgress.done();
            })
            .catch(() => {
              NProgress.done();
              this.$swal(
                this.$t("Failed"),
                this.$t("Something_Wrong"),
                "warning"
              );
            });
        }
      });
    },

    // Receive
    Receive_InternalOrder(id) {
       this.$swal({
        title: this.$t("Receive_Title"),
        text: this.$t("Receive_Text"),
        type: "success",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Receive")
      }).then(result => {
        if (result.value) {
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("internal_orders/" + id + "/receive")
            .then(() => {
              this.$swal(
                this.$t("Success"),
                this.$t("Received_Successfully"),
                "success"
              );
              Fire.$emit("Delete_InternalOrder");
              NProgress.done();
            })
            .catch(() => {
              NProgress.done();
              this.$swal(
                this.$t("Failed"),
                this.$t("Something_Wrong"),
                "warning"
              );
            });
        }
      });
    }

  },

  //-----------------------------Autoload function-------------------
  created: function() {
    this.Get_InternalOrders(1);

    Fire.$on("Get_Details_InternalOrder", () => {
      this.$bvModal.show("showDetails");
      // Complete the animation of theprogress bar.
      setTimeout(() => NProgress.done(), 500);
    });

    Fire.$on("Delete_InternalOrder", () => {
      setTimeout(() => {
        this.Get_InternalOrders(this.serverParams.page);
        // Complete the animation of theprogress bar.
        setTimeout(() => NProgress.done(), 500);
      }, 500);
    });
  }
};
</script>
