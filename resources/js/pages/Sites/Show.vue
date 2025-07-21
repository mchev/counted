<template>
  <AppLayout>

      <div class="flex items-center justify-between p-4">
        <div>
          <Heading :title="`${site.name} - Analytics`" />
          <p class="text-sm text-muted-foreground">{{ site.domain }}</p>
        </div>
        <div class="flex items-center space-x-2">
          <!-- Date Range Picker -->
          <Popover>
            <PopoverTrigger as-child>
              <Button variant="outline" class="justify-start text-left font-normal">
                <Icon name="calendar" class="mr-2 h-4 w-4" />
                {{ formatDateRange }}
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0" align="end">
              <div class="p-4">
                <div class="space-y-4">
                  <div>
                    <label class="text-sm font-medium">From Date</label>
                    <input 
                      type="date" 
                      v-model="dateFrom"
                      @change="updateCustomDateRange"
                      class="w-full mt-1 px-3 py-2 border rounded-md text-sm"
                    />
                  </div>
                  <div>
                    <label class="text-sm font-medium">To Date</label>
                    <input 
                      type="date" 
                      v-model="dateTo"
                      @change="updateCustomDateRange"
                      class="w-full mt-1 px-3 py-2 border rounded-md text-sm"
                    />
                  </div>
                  <Button 
                    @click="applyCustomDateRange"
                    size="sm"
                    class="w-full"
                  >
                    Apply Range
                  </Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>

          <!-- Quick Period Selector -->
          <select
            v-model="selectedPeriod"
            @change="updatePeriod"
            class="px-3 py-2 border rounded-md text-sm bg-background"
          >
            <option value="1d">Last 24 hours</option>
            <option value="2d">Last 48 hours</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="90d">Last 90 days</option>
            <option value="1y">Last year</option>
          </select>

          <Button as="a" :href="route('sites.edit', { site: site.id })" variant="outline">
            <Icon name="settings" class="mr-2 h-4 w-4" />
            Settings
          </Button>
        </div>
      </div>


    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <!-- Enhanced Stats Overview -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Page Views</CardTitle>
            <Icon name="eye" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ analyticsData.stats.totalPageViews.toLocaleString() }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Unique Visitors</CardTitle>
            <Icon name="users" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ analyticsData.stats.uniqueVisitors.toLocaleString() }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Bounce Rate</CardTitle>
            <Icon name="arrow-up-right" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ analyticsData.stats.bounceRate }}%</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Avg. Time</CardTitle>
            <Icon name="clock" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ formatTime(analyticsData.stats.avgTimeOnPage) }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Events</CardTitle>
            <Icon name="activity" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ analyticsData.stats.totalEvents.toLocaleString() }}</div>
          </CardContent>
        </Card>
      </div>

      <!-- Combined Analytics Chart -->
      <Card>
        <CardHeader>
          <CardTitle>Traffic Overview</CardTitle>
          <CardDescription>
            Page views and unique visitors {{ analyticsData.chartData.isHourly ? 'by hour' : 'over time' }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="h-80">
            <AnalyticsChart
              :data="combinedChartData"
              type="line"
              :height="320"
            />
          </div>
        </CardContent>
      </Card>

      <!-- Top Content & Referrers -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Top Pages -->
        <Card>
          <CardHeader>
            <CardTitle>Top Pages</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.topPages.length === 0" class="text-center py-8 text-muted-foreground">
              No page views yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(page, index) in analyticsData.topPages"
                :key="index"
                class="flex items-center justify-between"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ page.url }}</p>
                </div>
                <div class="text-sm text-muted-foreground">{{ page.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Top Referrers -->
        <Card>
          <CardHeader>
            <CardTitle>Top Referrers</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.topReferrers.length === 0" class="text-center py-8 text-muted-foreground">
              No referrer data yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(referrer, index) in analyticsData.topReferrers"
                :key="index"
                class="flex items-center justify-between"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ referrer.referrer }}</p>
                </div>
                <div class="text-sm text-muted-foreground">{{ referrer.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Device & Browser Stats -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Device Types -->
        <Card>
          <CardHeader>
            <CardTitle>Device Types</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.deviceStats.length === 0" class="text-center py-8 text-muted-foreground">
              No device data yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(device, index) in analyticsData.deviceStats"
                :key="index"
                class="flex items-center justify-between"
              >
                <div class="flex items-center space-x-2">
                  <Icon
                    :name="getDeviceIcon(device.device)"
                    class="h-4 w-4 text-muted-foreground"
                  />
                  <span class="text-sm font-medium capitalize">{{ device.device }}</span>
                </div>
                <div class="text-sm text-muted-foreground">{{ device.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Browsers -->
        <Card>
          <CardHeader>
            <CardTitle>Top Browsers</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.browserStats.length === 0" class="text-center py-8 text-muted-foreground">
              No browser data yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(browser, index) in analyticsData.browserStats"
                :key="index"
                class="flex items-center justify-between"
              >
                <span class="text-sm font-medium">{{ browser.browser }}</span>
                <div class="text-sm text-muted-foreground">{{ browser.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Operating Systems -->
        <Card>
          <CardHeader>
            <CardTitle>Operating Systems</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.osStats.length === 0" class="text-center py-8 text-muted-foreground">
              No OS data yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(os, index) in analyticsData.osStats"
                :key="index"
                class="flex items-center justify-between"
              >
                <span class="text-sm font-medium">{{ os.os }}</span>
                <div class="text-sm text-muted-foreground">{{ os.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Screen Resolutions & Top Events -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Screen Resolutions -->
        <Card>
          <CardHeader>
            <CardTitle>Screen Resolutions</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.screenStats.length === 0" class="text-center py-8 text-muted-foreground">
              No screen data yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(screen, index) in analyticsData.screenStats"
                :key="index"
                class="flex items-center justify-between"
              >
                <span class="text-sm font-medium">{{ screen.resolution }}</span>
                <div class="text-sm text-muted-foreground">{{ screen.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Top Events -->
        <Card>
          <CardHeader>
            <CardTitle>Top Events</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="analyticsData.topEvents.length === 0" class="text-center py-8 text-muted-foreground">
              No events tracked yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(event, index) in analyticsData.topEvents"
                :key="index"
                class="flex items-center justify-between"
              >
                <span class="text-sm font-medium">{{ event.name }}</span>
                <div class="text-sm text-muted-foreground">{{ event.count.toLocaleString() }}</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Recent Activity -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent Page Views -->
        <Card>
          <CardHeader>
            <CardTitle>Recent Page Views</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="site.page_views.length === 0" class="text-center py-8 text-muted-foreground">
              No page views yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="pageView in site.page_views"
                :key="pageView.id"
                class="flex items-center justify-between"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ pageView.url }}</p>
                  <p class="text-xs text-muted-foreground">
                    {{ new Date(pageView.created_at).toLocaleString() }}
                  </p>
                </div>
                <div class="text-xs text-muted-foreground">
                  {{ pageView.device_type || 'Unknown' }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Recent Events -->
        <Card>
          <CardHeader>
            <CardTitle>Recent Events</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="site.events.length === 0" class="text-center py-8 text-muted-foreground">
              No events tracked yet
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="event in site.events"
                :key="event.id"
                class="flex items-center justify-between"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium">{{ event.name }}</p>
                  <p class="text-xs text-muted-foreground">
                    {{ new Date(event.created_at).toLocaleString() }}
                  </p>
                </div>
                <div class="text-xs text-muted-foreground">
                  {{ getHostname(event.url) }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'
import AnalyticsChart from '@/components/AnalyticsChart.vue'

interface PageView {
  id: number
  url: string
  device_type: string
  created_at: string
}

interface Event {
  id: number
  name: string
  url: string
  created_at: string
}

interface Site {
  id: number
  name: string
  domain: string
  tracking_id: string
  tracking_script: string
  is_active: boolean
  page_views_count: number
  events_count: number
  page_views: PageView[]
  events: Event[]
}

interface AnalyticsData {
  chartData: {
    labels: string[]
    pageViews: number[]
    visitors: number[]
    isHourly: boolean
  }
  stats: {
    totalPageViews: number
    uniqueVisitors: number
    bounceRate: number
    avgTimeOnPage: number
    totalEvents: number
  }
  topPages: Array<{ url: string; count: number }>
  topReferrers: Array<{ referrer: string; count: number }>
  deviceStats: Array<{ device: string; count: number }>
  browserStats: Array<{ browser: string; count: number }>
  osStats: Array<{ os: string; count: number }>
  screenStats: Array<{ resolution: string; count: number }>
  topEvents: Array<{ name: string; count: number }>
}

interface Props {
  site: Site
  analyticsData: AnalyticsData
  period?: string
}

const props = defineProps<Props>()

// Reactive properties
const selectedPeriod = ref(props.period || '7d')
const dateFrom = ref(new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0])
const dateTo = ref(new Date().toISOString().split('T')[0])

// Methods
const updatePeriod = () => {
  router.get(route('sites.show', { site: props.site.id }), { period: selectedPeriod.value }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const updateCustomDateRange = () => {
  // This will be called when date inputs change
}

const applyCustomDateRange = () => {
  if (dateFrom.value && dateTo.value) {
    const fromDate = new Date(dateFrom.value)
    const toDate = new Date(dateTo.value)
    const daysDiff = Math.ceil((toDate.getTime() - fromDate.getTime()) / (1000 * 60 * 60 * 24))
    
    // Convert to closest period, with special handling for short ranges
    let period = '7d'
    if (daysDiff <= 1) period = '1d'
    else if (daysDiff <= 2) period = '2d'
    else if (daysDiff <= 7) period = '7d'
    else if (daysDiff <= 30) period = '30d'
    else if (daysDiff <= 90) period = '90d'
    else period = '1y'
    
    selectedPeriod.value = period
    updatePeriod()
  }
}

const formatTime = (seconds: number): string => {
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes}m ${remainingSeconds}s`
}

const getDeviceIcon = (device: string): string => {
  switch (device.toLowerCase()) {
    case 'mobile': return 'smartphone'
    case 'tablet': return 'tablet'
    case 'desktop': return 'monitor'
    default: return 'monitor'
  }
}

// Computed properties
const formatDateRange = computed(() => {
  if (dateFrom.value && dateTo.value) {
    const from = new Date(dateFrom.value).toLocaleDateString()
    const to = new Date(dateTo.value).toLocaleDateString()
    return `${from} - ${to}`
  }
  return 'Custom Range'
})

const combinedChartData = computed(() => ({
  labels: props.analyticsData.chartData.labels,
  datasets: [
    {
      label: 'Page Views',
      data: props.analyticsData.chartData.pageViews,
      borderColor: '#6366f1',
      backgroundColor: 'rgba(99, 102, 241, 0.1)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointRadius: 0,
      pointHoverRadius: 6,
      pointHoverBackgroundColor: '#6366f1',
      pointHoverBorderColor: '#ffffff',
      pointHoverBorderWidth: 2,
    },
    {
      label: 'Unique Visitors',
      data: props.analyticsData.chartData.visitors,
      borderColor: '#10b981',
      backgroundColor: 'rgba(16, 185, 129, 0.1)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointRadius: 0,
      pointHoverRadius: 6,
      pointHoverBackgroundColor: '#10b981',
      pointHoverBorderColor: '#ffffff',
      pointHoverBorderWidth: 2,
    }
  ]
}))

const getHostname = (url: string): string => {
  try {
    return new URL(url).hostname
  } catch {
    return 'N/A'
  }
}
</script> 