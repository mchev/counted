<template>
  <Card>
    <CardHeader>
      <CardTitle>Top Events</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="events.length === 0" class="text-center py-8 text-muted-foreground">
        No events tracked yet
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="(event, index) in events"
          :key="index"
          class="flex items-center justify-between"
        >
          <span class="text-sm font-medium">{{ event.name }}</span>
          <div class="text-sm text-muted-foreground">{{ event.count.toLocaleString() }}</div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface EventStat {
  name: string
  count: number
}

interface Props {
  events: EventStat[]
}

defineProps<Props>()
</script> 