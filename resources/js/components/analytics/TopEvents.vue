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
</template>

<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Event {
  id: number
  name: string
  url: string
  created_at: string
}

interface Props {
  events: Event[]
}

defineProps<Props>()

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
</script> 