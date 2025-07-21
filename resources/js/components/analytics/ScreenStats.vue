<template>
  <Card>
    <CardHeader>
      <CardTitle>Screen Resolutions</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="screens.length === 0" class="text-center py-8 text-muted-foreground">
        No screen data yet
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="(screen, index) in screens"
          :key="index"
          class="flex items-center justify-between"
        >
          <span class="text-sm font-medium">{{ screen.resolution }}</span>
          <div class="text-sm text-muted-foreground">{{ screen.count.toLocaleString() }}</div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Screen {
  resolution: string
  count: number
}

interface Props {
  screens: Screen[]
}

defineProps<Props>()
</script> 