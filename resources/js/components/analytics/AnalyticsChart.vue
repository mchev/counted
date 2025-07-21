<template>
  <Card>
    <CardHeader>
      <CardTitle>Traffic Overview</CardTitle>
      <CardDescription>
        Page views and unique visitors {{ 
          selectedGranularity === 'hourly' ? 'by hour' : 
          selectedGranularity === 'daily' ? 'by day' : 
          'by month' 
        }}
      </CardDescription>
    </CardHeader>
    <CardContent>
      <div class="h-80">
        <div class="relative w-full h-full">
          <canvas ref="chartRef" class="w-full h-full"></canvas>
        </div>
      </div>
      <!-- Granularity Selector -->
      <div class="mt-4 flex items-center justify-center space-x-2">
        <div v-if="isGranularityLocked" class="text-xs text-muted-foreground">
          Auto-set to hours for short periods
        </div>
        <div class="flex items-center space-x-1 bg-muted rounded-lg p-1" :class="{ 'opacity-50': isGranularityLocked }">
          <button
            @click="!isGranularityLocked && setGranularity('hourly')"
            :disabled="isGranularityLocked"
            :class="[
              'px-3 py-1 text-xs font-medium rounded-md transition-colors',
              selectedGranularity === 'hourly'
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground',
              isGranularityLocked ? 'cursor-not-allowed' : 'cursor-pointer'
            ]"
          >
            Hours
          </button>
          <button
            @click="!isGranularityLocked && setGranularity('daily')"
            :disabled="isGranularityLocked"
            :class="[
              'px-3 py-1 text-xs font-medium rounded-md transition-colors',
              selectedGranularity === 'daily'
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground',
              isGranularityLocked ? 'cursor-not-allowed' : 'cursor-pointer'
            ]"
          >
            Days
          </button>
          <button
            @click="!isGranularityLocked && setGranularity('monthly')"
            :disabled="isGranularityLocked"
            :class="[
              'px-3 py-1 text-xs font-medium rounded-md transition-colors',
              selectedGranularity === 'monthly'
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground',
              isGranularityLocked ? 'cursor-not-allowed' : 'cursor-pointer'
            ]"
          >
            Months
          </button>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Chart, ChartConfiguration, registerables } from 'chart.js'

// Register Chart.js components
Chart.register(...registerables)

interface ChartData {
  labels: string[]
  pageViews: number[]
  visitors: number[]
  isHourly: boolean
}

interface Props {
  chartData: ChartData
  selectedGranularity: string
  isGranularityLocked: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  setGranularity: [granularity: 'hourly' | 'daily' | 'monthly']
}>()

const setGranularity = (granularity: 'hourly' | 'daily' | 'monthly') => {
  emit('setGranularity', granularity)
}

const chartRef = ref<HTMLCanvasElement>()
let chart: Chart | null = null

const combinedChartData = computed(() => ({
  labels: props.chartData.labels,
  datasets: [
    {
      label: 'Page Views',
      data: props.chartData.pageViews,
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
      data: props.chartData.visitors,
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

const createChart = () => {
  if (!chartRef.value) return

  const ctx = chartRef.value.getContext('2d')
  if (!ctx) return

  const config: ChartConfiguration = {
    type: 'line',
    data: combinedChartData.value,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        intersect: false,
        mode: 'index' as const,
      },
      plugins: {
        legend: {
          display: false, // Hide legend like Fathom
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: 'white',
          bodyColor: 'white',
          borderColor: 'rgba(255, 255, 255, 0.1)',
          borderWidth: 1,
          cornerRadius: 8,
          displayColors: true,
          padding: 12,
          titleFont: {
            size: 14,
            weight: 'bold',
          },
          bodyFont: {
            size: 13,
          },
        },
      },
      scales: {
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          beginAtZero: true,
          grid: {
            color: 'rgba(255, 255, 255, 0.1)',
          },
          ticks: {
            color: 'rgba(255, 255, 255, 0.8)',
            font: {
              size: 12,
            },
            padding: 8,
          },
        },
        x: {
          grid: {
            color: 'rgba(255, 255, 255, 0.1)',
          },
          ticks: {
            color: 'rgba(255, 255, 255, 0.8)',
            font: {
              size: 12,
            },
            padding: 8,
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 10,
          },
        },
      },
    },
  }

  chart = new Chart(ctx, config)
}

const destroyChart = () => {
  if (chart) {
    chart.destroy()
    chart = null
  }
}

onMounted(() => {
  createChart()
})

onUnmounted(() => {
  destroyChart()
})

watch(() => combinedChartData.value, () => {
  if (chart) {
    chart.data = combinedChartData.value
    chart.update()
  }
}, { deep: true })
</script> 