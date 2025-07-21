<template>
  <Card>
    <CardHeader>
      <CardTitle>Top Referrers</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="referrers.length === 0" class="text-center py-8 text-muted-foreground">
        No referrer data yet
      </div>
      <div v-else>
        <!-- Header row -->
        <div class="flex items-center justify-between pb-2 border-b border-border">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Referrers</p>
          </div>
          <div class="flex items-center space-x-3">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wide w-16 text-right">Visitors</div>
          </div>
        </div>
        <!-- Data rows -->
        <div class="space-y-3 pt-3">
          <div
            v-for="(referrer, index) in referrers"
            :key="index"
            class="flex items-center justify-between"
          >
            <div class="flex items-center space-x-3 flex-1 min-w-0">
              <!-- Favicon -->
              <div class="flex-shrink-0">
                <div v-if="referrer.referrer === null" class="w-5 h-5 bg-muted rounded flex items-center justify-center">
                  <Icon name="home" class="h-3 w-3 text-muted-foreground" />
                </div>
                <div v-else class="w-5 h-5 bg-muted rounded flex items-center justify-center">
                  <img 
                    v-if="referrer.favicon && !referrer.faviconError"
                    :src="referrer.favicon"
                    :alt="getHostname(referrer.referrer)"
                    class="w-5 h-5 rounded"
                    @error="(e) => handleFaviconError(e, referrer)"
                  />
                  <Icon 
                    v-else
                    name="globe" 
                    class="h-3 w-3 text-muted-foreground" 
                  />
                </div>
              </div>
              <!-- Referrer text -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate">
                  {{ referrer.referrer === null ? 'Direct Access' : getHostname(referrer.referrer) }}
                </p>
                <p v-if="referrer.referrer !== null" class="text-xs text-muted-foreground truncate">
                  {{ referrer.referrer }}
                </p>
              </div>
            </div>
            <div class="flex items-center space-x-3">
              <div class="text-sm text-muted-foreground">{{ referrer.count.toLocaleString() }}</div>
              <div class="text-sm text-muted-foreground w-12 text-right">
                {{ getReferrerPercentage(referrer.count) }}
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
import Icon from '@/components/Icon.vue'

interface Referrer {
  referrer: string | null
  count: number
  favicon: string | null
  faviconError?: boolean
}

interface Props {
  referrers: Referrer[]
}

const props = defineProps<Props>()

// Calculate total visitors from referrers for percentage calculation
const totalReferrerVisitors = computed(() => {
  return props.referrers.reduce((total, referrer) => total + referrer.count, 0)
})

const getReferrerPercentage = (count: number): string => {
  if (totalReferrerVisitors.value === 0) return '0%'
  const percentage = (count / totalReferrerVisitors.value) * 100
  return percentage < 1 ? '<1%' : `${Math.round(percentage)}%`
}

const getHostname = (url: string): string => {
  try {
    // Add protocol if missing
    let urlToParse = url
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      urlToParse = 'https://' + url
    }
    return new URL(urlToParse).hostname
  } catch {
    return 'N/A'
  }
}

const handleFaviconError = (event: any, referrer: any) => {
  // Mark this referrer's favicon as failed
  referrer.faviconError = true
}
</script> 