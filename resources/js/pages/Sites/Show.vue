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
      <AnalyticsStats :stats="analyticsData.stats" />

      <AnalyticsChart 
        :chart-data="analyticsData.chartData"
        :selected-granularity="selectedGranularity"
        :is-granularity-locked="isGranularityLocked"
        @set-granularity="setGranularity"
      />

      <!-- Top Content & Referrers -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <TopPages :pages="analyticsData.topPages" />

        <TopReferrers :referrers="analyticsData.topReferrers" />
      </div>

      <!-- Device & Browser Stats -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <DeviceStats :devices="analyticsData.deviceStats" />

        <BrowserStats :browsers="analyticsData.browserStats" />

        <OsStats :operating-systems="analyticsData.osStats" />
      </div>

      <!-- Screen Resolutions & Top Events -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ScreenStats :screens="analyticsData.screenStats" />

        <TopEventStats :events="analyticsData.topEvents" />
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

        <TopEvents :events="site.events" />
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
import { TopPages, TopReferrers, DeviceStats, BrowserStats, OsStats, ScreenStats, TopEvents, TopEventStats, AnalyticsStats, AnalyticsChart } from '@/components/analytics'

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
  topReferrers: Array<{ referrer: string | null; count: number; favicon: string | null; faviconError?: boolean }>
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
  granularity?: string
}

const props = defineProps<Props>()

// Reactive properties
const selectedPeriod = ref(props.period || '7d')
const dateFrom = ref(new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0])
const dateTo = ref(new Date().toISOString().split('T')[0])
const selectedGranularity = ref(props.granularity || 'daily') // Initialize with prop or default to daily

// Methods
const updatePeriod = () => {
  router.get(route('sites.show', { site: props.site.id }), { 
    period: selectedPeriod.value,
    granularity: selectedGranularity.value 
  }, {
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

const setGranularity = (granularity: 'hourly' | 'daily' | 'monthly') => {
  selectedGranularity.value = granularity
  // Reload data with new granularity
  router.get(route('sites.show', { site: props.site.id }), { 
    period: selectedPeriod.value,
    granularity: granularity 
  }, {
    preserveState: true,
    preserveScroll: true,
  })
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





// Check if granularity should be locked to hourly
const isGranularityLocked = computed(() => {
  return selectedGranularity.value === 'hourly' && (selectedPeriod.value === '1d' || selectedPeriod.value === '2d')
})
</script> 