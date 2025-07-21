<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'

interface ImportHistory {
  id: number
  site_name: string
  status: 'processing' | 'completed' | 'failed'
  type: string
  file_name: string
  summary?: string
  details?: any
  created_at: string
  updated_at: string
}

interface Props {
  imports: ImportHistory[]
}

const props = defineProps<Props>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Import',
    href: '/import',
  },
  {
    title: 'History',
    href: '/import/history',
  },
]

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'completed':
      return 'check-circle'
    case 'failed':
      return 'x-circle'
    case 'processing':
      return 'loader-2'
    default:
      return 'help-circle'
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'completed':
      return 'text-green-600'
    case 'failed':
      return 'text-red-600'
    case 'processing':
      return 'text-blue-600'
    default:
      return 'text-gray-600'
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}

const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>

<template>
  <Head title="Import History" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex items-center justify-between">
        <Heading title="Import History" />
        <Button as="a" :href="route('import.index')" variant="outline">
          <Icon name="arrow-left" class="mr-2 h-4 w-4" />
          Back to Import
        </Button>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Stats Overview -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Total Imports</CardTitle>
            <Icon name="database" class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ imports.length }}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Successful</CardTitle>
            <Icon name="check-circle" class="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-green-600">
              {{ imports.filter(imp => imp.status === 'completed').length }}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Failed</CardTitle>
            <Icon name="x-circle" class="h-4 w-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold text-red-600">
              {{ imports.filter(imp => imp.status === 'failed').length }}
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Import History List -->
      <Card>
        <CardHeader>
          <CardTitle>Import History</CardTitle>
          <CardDescription>
            Recent import attempts and their status
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="imports.length === 0" class="text-center py-8">
            <Icon name="database" class="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 class="mt-2 text-sm font-semibold">No imports yet</h3>
            <p class="mt-1 text-sm text-muted-foreground">
              Start by importing your first Umami dump.
            </p>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="importItem in imports"
              :key="importItem.id"
              class="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <Icon 
                      :name="getStatusIcon(importItem.status)" 
                      :class="`h-4 w-4 ${getStatusColor(importItem.status)}`"
                    />
                    <span class="font-medium">{{ importItem.site_name }}</span>
                    <span 
                      :class="['text-xs px-2 py-1 rounded-full', 
                        importItem.status === 'completed' ? 'bg-green-100 text-green-800' :
                        importItem.status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-blue-100 text-blue-800'
                      ]"
                    >
                      {{ importItem.status }}
                    </span>
                  </div>
                  
                  <div class="text-sm text-muted-foreground space-y-1">
                    <div class="flex items-center space-x-4">
                      <span>File: {{ importItem.file_name }}</span>
                      <span>Type: {{ importItem.type }}</span>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                      <span>Started: {{ formatDate(importItem.created_at) }}</span>
                      <span v-if="importItem.updated_at !== importItem.created_at">
                        Updated: {{ formatDate(importItem.updated_at) }}
                      </span>
                    </div>

                    <div v-if="importItem.details?.file_size_formatted" class="flex items-center space-x-4">
                      <span>Size: {{ importItem.details.file_size_formatted }}</span>
                      <span v-if="importItem.details.websites_count">
                        Sites: {{ importItem.details.websites_count }}
                      </span>
                    </div>

                    <div v-if="importItem.summary" class="mt-2 p-2 bg-muted rounded text-xs">
                      {{ importItem.summary }}
                    </div>

                    <!-- Error Details -->
                    <div v-if="importItem.status === 'failed' && importItem.details" class="mt-2">
                      <details class="text-xs">
                        <summary class="cursor-pointer text-red-600 font-medium">
                          View Error Details
                        </summary>
                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded">
                          <pre class="whitespace-pre-wrap text-xs">{{ JSON.stringify(importItem.details, null, 2) }}</pre>
                        </div>
                      </details>
                    </div>

                    <!-- Success Details -->
                    <div v-if="importItem.status === 'completed' && importItem.details" class="mt-2">
                      <details class="text-xs">
                        <summary class="cursor-pointer text-green-600 font-medium">
                          View Import Details
                        </summary>
                        <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                          <div class="grid grid-cols-2 gap-4 text-xs">
                            <div v-if="importItem.details.page_views">
                              <strong>Page Views:</strong> {{ importItem.details.page_views.toLocaleString() }}
                            </div>
                            <div v-if="importItem.details.events">
                              <strong>Events:</strong> {{ importItem.details.events.toLocaleString() }}
                            </div>
                            <div v-if="importItem.details.websites_created">
                              <strong>Sites Created:</strong> {{ importItem.details.websites_created }}
                            </div>
                            <div v-if="importItem.details.websites_updated">
                              <strong>Sites Updated:</strong> {{ importItem.details.websites_updated }}
                            </div>
                            <div v-if="importItem.details.batches_processed">
                              <strong>Batches:</strong> {{ importItem.details.batches_processed }}
                            </div>
                            <div v-if="importItem.details.errors">
                              <strong>Errors:</strong> {{ importItem.details.errors }}
                            </div>
                          </div>
                        </div>
                      </details>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template> 