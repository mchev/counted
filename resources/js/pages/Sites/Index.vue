<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <Heading title="My Sites" />
        <div class="flex items-center space-x-2">
          <Button as="a" :href="route('import.index')" variant="outline">
            <Icon name="upload" class="mr-2 h-4 w-4" />
            Import Data
          </Button>
          <Button as="a" :href="route('sites.create')" variant="default">
            <Icon name="plus" class="mr-2 h-4 w-4" />
            Add Site
          </Button>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Your Websites</CardTitle>
          <CardDescription>
            Manage your websites and view their analytics
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="sites.length === 0" class="text-center py-8">
            <Icon name="globe" class="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 class="mt-2 text-sm font-semibold">No sites yet</h3>
            <p class="mt-1 text-sm text-muted-foreground">
              Get started by adding your first website to track analytics.
            </p>
            <div class="mt-6">
              <Button as="a" :href="route('sites.create')" variant="default">
                <Icon name="plus" class="mr-2 h-4 w-4" />
                Add Your First Site
              </Button>
            </div>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="site in sites"
              :key="site.id"
              class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
            >
              <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                  <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                    <Icon name="globe" class="h-5 w-5 text-primary" />
                  </div>
                </div>
                <div>
                  <h3 class="text-sm font-medium">{{ site.name }}</h3>
                  <p class="text-sm text-muted-foreground">{{ site.domain }}</p>
                  <div class="flex items-center space-x-4 mt-1 text-xs text-muted-foreground">
                    <span>{{ site.page_views_count }} page views</span>
                    <span>{{ site.events_count }} events</span>
                    <span
                      :class="site.is_active ? 'text-green-600' : 'text-red-600'"
                    >
                      {{ site.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <Button
                  as="a"
                  :href="route('sites.show', { site: site.id })"
                  variant="outline"
                  size="sm"
                >
                  <Icon name="bar-chart" class="mr-2 h-4 w-4" />
                  View Analytics
                </Button>
                <Button
                  as="a"
                  :href="route('sites.edit', { site: site.id })"
                  variant="outline"
                  size="sm"
                >
                  <Icon name="settings" class="mr-2 h-4 w-4" />
                  Edit
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'

interface Site {
  id: number
  name: string
  domain: string
  is_active: boolean
  page_views_count: number
  events_count: number
}

interface Props {
  sites: Site[]
}

defineProps<Props>()
</script> 