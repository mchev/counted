<template>
  <Card>
    <CardHeader>
      <CardTitle>Device Types</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="devices.length === 0" class="text-center py-8 text-muted-foreground">
        No device data yet
      </div>
      <div v-else>
        <!-- Header row -->
        <div class="flex items-center justify-between pb-2 border-b border-border">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Devices</p>
          </div>
          <div class="flex items-center space-x-3">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wide w-16 text-right">Users</div>
          </div>
        </div>
        <!-- Data rows -->
        <div class="space-y-3 pt-3">
          <div
            v-for="(device, index) in devices"
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
            <div class="flex items-center space-x-3">
              <div class="text-sm text-muted-foreground">{{ device.count.toLocaleString() }}</div>
              <div class="text-sm text-muted-foreground w-12 text-right">
                {{ getDevicePercentage(device.count) }}
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

interface Device {
  device: string
  count: number
}

interface Props {
  devices: Device[]
}

const props = defineProps<Props>()

// Calculate total device users for percentage calculation
const totalDeviceUsers = computed(() => {
  return props.devices.reduce((total, device) => total + device.count, 0)
})

const getDevicePercentage = (count: number): string => {
  if (totalDeviceUsers.value === 0) return '0%'
  const percentage = (count / totalDeviceUsers.value) * 100
  return percentage < 1 ? '<1%' : `${Math.round(percentage)}%`
}

const getDeviceIcon = (device: string): string => {
  switch (device.toLowerCase()) {
    case 'mobile': return 'smartphone'
    case 'tablet': return 'tablet'
    case 'desktop': return 'monitor'
    default: return 'monitor'
  }
}
</script> 