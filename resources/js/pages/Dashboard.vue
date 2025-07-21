<script setup lang="ts">
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'

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

const props = withDefaults(defineProps<Props>(), {
  sites: () => []
})

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
]

const totalPageViews = computed(() => 
  props.sites.reduce((sum, site) => sum + site.page_views_count, 0)
)

const totalEvents = computed(() => 
  props.sites.reduce((sum, site) => sum + site.events_count, 0)
)

const activeSites = computed(() => 
  props.sites.filter(site => site.is_active).length
)
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <div class="flex items-center justify-between">
                <Heading title="Analytics Dashboard" />
                <Button as="a" :href="route('sites.create')" variant="default">
                    <Icon name="plus" class="mr-2 h-4 w-4" />
                    Add Site
                </Button>
            </div>
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Sites</CardTitle>
                        <Icon name="globe" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ sites.length }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Page Views</CardTitle>
                        <Icon name="eye" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalPageViews }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Events</CardTitle>
                        <Icon name="activity" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalEvents }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Sites</CardTitle>
                        <Icon name="check-circle" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ activeSites }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sites List -->
            <Card>
                <CardHeader>
                    <CardTitle>Your Sites</CardTitle>
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
                                    :href="route('sites.show', { site: site.id })"
                                    variant="outline"
                                    size="sm"
                                >
                                    <Icon name="settings" class="mr-2 h-4 w-4" />
                                    Settings
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
