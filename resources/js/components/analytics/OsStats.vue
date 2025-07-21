<template>
  <Card>
    <CardHeader>
      <CardTitle>Operating Systems</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="operatingSystems.length === 0" class="text-center py-8 text-muted-foreground">
        No OS data yet
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="(os, index) in operatingSystems"
          :key="index"
          class="flex items-center justify-between"
        >
          <span class="text-sm font-medium">{{ os.os }}</span>
          <div class="text-sm text-muted-foreground">{{ os.count.toLocaleString() }}</div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface OperatingSystem {
  os: string
  count: number
}

interface Props {
  operatingSystems: OperatingSystem[]
}

defineProps<Props>()
</script> 