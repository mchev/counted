<template>
  <Card>
    <CardHeader>
      <CardTitle>Top Browsers</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="browsers.length === 0" class="text-center py-8 text-muted-foreground">
        No browser data yet
      </div>
      <div v-else>
        <!-- Header row -->
        <div class="flex items-center justify-between pb-2 border-b border-border">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Browsers</p>
          </div>
          <div class="flex items-center space-x-3">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wide w-16 text-right">Users</div>
          </div>
        </div>
        <!-- Data rows -->
        <div class="space-y-3 pt-3">
          <div
            v-for="(browser, index) in browsers"
            :key="index"
            class="flex items-center justify-between"
          >
            <span class="text-sm font-medium">{{ browser.browser }}</span>
            <div class="flex items-center space-x-3">
              <div class="text-sm text-muted-foreground">{{ browser.count.toLocaleString() }}</div>
              <div class="text-sm text-muted-foreground w-12 text-right">
                {{ getBrowserPercentage(browser.count) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Browser {
  browser: string
  count: number
}

interface Props {
  browsers: Browser[]
}

const props = defineProps<Props>()

// Calculate total browser users for percentage calculation
const totalBrowserUsers = computed(() => {
  return props.browsers.reduce((total, browser) => total + browser.count, 0)
})

const getBrowserPercentage = (count: number): string => {
  if (totalBrowserUsers.value === 0) return '0%'
  const percentage = (count / totalBrowserUsers.value) * 100
  return percentage < 1 ? '<1%' : `${Math.round(percentage)}%`
}
</script> 