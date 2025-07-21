<template>
  <Card>
    <CardHeader>
      <CardTitle>Top Pages</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="pages.length === 0" class="text-center py-8 text-muted-foreground">
        No page views yet
      </div>
      <div v-else>
        <!-- Header row -->
        <div class="flex items-center justify-between pb-2 border-b border-border">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Pages</p>
          </div>
          <div class="flex items-center space-x-3">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wide w-16 text-right">Views</div>
          </div>
        </div>
        <!-- Data rows -->
        <div class="space-y-3 pt-3">
          <div
            v-for="(page, index) in pages"
            :key="index"
            class="flex items-center justify-between"
          >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate">{{ page.url }}</p>
            </div>
            <div class="flex items-center space-x-3">
              <div class="text-sm text-muted-foreground">{{ page.count.toLocaleString() }}</div>
              <div class="text-sm text-muted-foreground w-12 text-right">
                {{ getPagePercentage(page.count) }}
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

interface Page {
  url: string
  count: number
}

interface Props {
  pages: Page[]
}

const props = defineProps<Props>()

// Calculate total page views for percentage calculation
const totalPageViews = computed(() => {
  return props.pages.reduce((total, page) => total + page.count, 0)
})

const getPagePercentage = (count: number): string => {
  if (totalPageViews.value === 0) return '0%'
  const percentage = (count / totalPageViews.value) * 100
  return percentage < 1 ? '<1%' : `${Math.round(percentage)}%`
}
</script> 