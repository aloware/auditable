<template>
    <div>
        <div class="row">
            <div class="col-md-6">
                <span class="page-anchor"
                        id="cnam_caller_id">
                </span>
                <h2 class="mb-2"><slot></slot></h2>

                <span>Total Logs: {{ this.pagination.total }}</span>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <user-selector :ignore_focus_mode="true"
                               @change="onFilterChange">
                </user-selector>
            </div>
            <div class="col-md-4">
                <date-selector>
                </date-selector>
            </div>
            <div class="col-md-4">
                <select @change="onActionFilterChange($event)">
                    <option v-for="(action, index) in action_filters" :key="index" :value="generateOptionValue(action.filter)">
                        {{ action.name }}
                    </option>
                </select>
            </div>
        </div>

        <el-table
            class="w-full mt-3"
            v-loading="loading"
            stripe
            row-key="id"
            :data="items"
            data-testid="audits-table">

            <el-table-column type="expand" data-testid="audits-table-column" >
                <template v-slot="scope">
                    <div class="row align-items-top">
                        <div class="col-md-12">
                            <el-tooltip class="item pull-left"
                                        effect="dark"
                                        content="Click For More Info"
                                        data-testid="audits-tooltip-for-more-info"
                                        placement="top">
                                <span class="text-dark-blueish" title="Changes">
                                    <strong>Audit #{{ scope.row.id }}</strong>
                                    <hr />
                                    <div v-for="(change, attribute) in scope.row.changes" :key="attribute">
                                        <div><strong>Attribute</strong>: {{ attribute }}</div>

                                        <template v-if="change && change.constructor === Array">
                                            <div><strong>Before</strong>: {{change[0] === null ? 'null' : change[0]}}</div>
                                            <div><strong>After</strong>: {{change[1] === null ? 'null' : change[1]}}</div>
                                        </template>

                                        <div v-else><strong>Value</strong>: {{ change }}</div>

                                        <hr />
                                    </div>
                                </span>
                            </el-tooltip>
                        </div>
                    </div>
                </template>
            </el-table-column>

            <el-table-column
                label="User"
                prop="user.name"
                :min-width="300">
                <template v-slot="scope">
                    <div class="row d-flex align-items-center">
                        <div class="col-6">
                            <div class="row">
                                <div class="col-12 d-flex align-items-center justify-content-left" v-if="scope.row.user">
                                    <router-link :to="{ name: 'User Dialog', params: {user_id: scope.row.user.id }}">
                                        <el-tooltip class="item pull-left"
                                                    effect="dark"
                                                    content="Click For More Info"
                                                    placement="top">
                                            <div class="text-dark-greenish" :title="scope.row.user.name">
                                                {{ scope.row.user.name }}
                                            </div>
                                        </el-tooltip>
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </el-table-column>

            <el-table-column
                label="Action"
                min-width="600">
                <template v-slot="scope">
                    <div v-for="(label, index) in actionLabels(scope.row)" :key="index">
                        <hr v-if="index > 0" />
                        <el-tooltip class="item" effect="dark" :content="label.icon">
                            <i class="material-icons">{{ label.icon }}</i>
                        </el-tooltip>
                        <span v-html="label.text"></span>
                    </div>
                </template>
            </el-table-column>

            <el-table-column
                label="Time"
                prop="created_at"
                data-testid="audits-table-column"
                width="240">
                <template v-slot="scope">
                    <span class="text-greyish">{{ scope.row.created_at | fixDateTime }}</span>
                </template>
            </el-table-column>

            <template slot="empty">
                <span v-if="!loading">No Data</span>
                <span v-if="loading"></span>
            </template>
        </el-table>

        <div class="row mt-3">
            <div class="col-12">
                <el-button-group class="pull-right">
                    <el-button size="small"
                               icon="el-icon-arrow-left"
                               v-if="!pagination.prev_page_url || pagination_loading || loading"
                               disabled
                               plain>
                        Previous Page
                    </el-button>
                    <el-button type="success"
                               size="small"
                               icon="el-icon-arrow-left"
                               v-else
                               @click="previousPage">
                        Previous Page
                    </el-button>

                    <el-button size="small"
                               v-if="!pagination.next_page_url || pagination_loading || loading"
                               disabled
                               plain>
                        Next Page
                        <i class="el-icon-arrow-right"></i>
                    </el-button>
                    <el-button type="success"
                               size="small"
                               v-else
                               @click="nextPage">
                        Next Page
                        <i class="el-icon-arrow-right"></i>
                    </el-button>
                </el-button-group>
            </div>
        </div>
    </div>
</template>

<script>
import DateSelector from "../../../../../resources/assets/user/components/date-selector.vue";
import {mapGetters} from "vuex";
export default {
    props: {
        model_alias: {
            type: String,
            required: true
        },
        model_id: {
            required: true
        },
        action_label_resolver: {
            type:     Function,
            required: false,
        },
        action_filters:        {
            type:     Array,
            required: true,
        },
    },

    computed:{
      ...mapGetters({
        filter_options: 'getFilter'
      }),
    },

    components: {
      DateSelector
    },

    data() {
        return {
            loading: false,
            items: [],
            pagination: {},
            pagination_loading: false,
            table_height: 769,
            extra_height: 0,
            source: null,
            filter: {
                user_id: '',
                from: '',
                to: '',
            }
        }
    },

    created() {
        this.CancelToken = axios.CancelToken
        this.source = this.CancelToken.source()

        this.getAudits()
    },

    methods: {
        generateOptionValue(filter) {
            return Object.entries(filter)
                .map(([key, value]) => `${key}:${value}`)
                .join('|');
        },
        actionLabels(audit) {
            if (this.action_label_resolver) {
                return this.action_label_resolver(audit);
            }

            return [audit.event_type + ' - ' + audit.label];
        },

        getAudits(url = null) {
            url = url || '/api/audits/line/' + this.model_id

            this.source.cancel('getAudits canceled by the user.')
            this.source = this.CancelToken.source()

            this.loading = true
            this.pagination_loading = true

            return axios.get(url, {
                params: this.filter,
                cancelToken: this.source.token
            }).then(res => {
                this.items = res.data.data

                this.pagination = res.data
                delete this.pagination.data
                this.pagination_loading = false

                this.extra_height = 0
                this.resize()
                this.loading = false

                return Promise.resolve(res)
            }).catch(err => {
                if (axios.isCancel(err)) {
                    console.log('Request canceled', err.message)
                } else {
                    console.log(err)
                    this.loading = false
                    this.pagination_loading = false

                    return Promise.reject(err)
                }
            })
        },

        nextPage($event) {
            $event.target.blur()
            if (this.loading) {
                return
            }
            delete this.filter.page

            this.getAudits(this.pagination.next_page_url)
        },

        previousPage($event) {
            $event.target.blur()
            if (this.loading) {
                return
            }
            delete this.filter.page
            this.getAudits(this.pagination.prev_page_url)
        },

        resize() {
            if (this.items.length == 0) {
                this.table_height = 400
            } else if (this.items.length < this.pagination.per_page) {
                this.table_height = 59 + (this.items.length * 71)
            } else {
                this.table_height = 59 + (this.pagination.per_page * 71)
            }

            this.table_height += this.extra_height
        },

        onFilterChange(id) {
            this.filter.user_id=id

            this.getAudits()
        },

    },

    watch: {
        'filter_options.from_date': function (data) {
            this.filter.from=data

            this.getAudits()
        },

        'filter_options.to_date': function (data) {
            this.filter.to=data

            this.getAudits()
        },

    }
}
</script>
