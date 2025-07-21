<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <Heading :title="`${site.name} - Settings`" />
          <p class="text-sm text-muted-foreground">{{ site.domain }}</p>
        </div>
        <div class="flex items-center space-x-2">
          <Button as="a" :href="route('sites.show', { site: site.id })" variant="outline">
            <Icon name="bar-chart" class="mr-2 h-4 w-4" />
            View Analytics
          </Button>
        </div>
      </div>
    </template>

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <!-- Site Settings -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Site Information -->
        <Card>
          <CardHeader>
            <CardTitle>Site Information</CardTitle>
            <CardDescription>
              Update your site details and configuration
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="updateSite" class="space-y-4">
              <div>
                <Label for="name">Site Name</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  type="text"
                  placeholder="My Website"
                  required
                />
              </div>

              <div>
                <Label for="domain">Domain</Label>
                <Input
                  id="domain"
                  v-model="form.domain"
                  type="text"
                  placeholder="example.com"
                  required
                />
              </div>

              <div>
                <Label for="description">Description (Optional)</Label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="w-full px-3 py-2 border border-input bg-background text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 rounded-md"
                  placeholder="Brief description of your website"
                />
              </div>

              <div class="flex items-center space-x-2">
                <Checkbox
                  id="is_active"
                  v-model:checked="form.is_active"
                />
                <Label for="is_active">Active (Enable tracking)</Label>
              </div>

              <Button type="submit" :disabled="processing">
                <Icon name="save" class="mr-2 h-4 w-4" />
                {{ processing ? 'Saving...' : 'Save Changes' }}
              </Button>
            </form>
          </CardContent>
        </Card>

        <!-- Tracking Code -->
        <Card>
          <CardHeader>
            <CardTitle>Tracking Code</CardTitle>
            <CardDescription>
              Add this code to your website to start tracking analytics
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div>
                <Label for="tracking-code">JavaScript Code</Label>
                <div class="relative">
                  <textarea
                    id="tracking-code"
                    :value="site.tracking_script || 'Loading tracking code...'"
                    rows="8"
                    class="w-full px-3 py-2 border border-input bg-background text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 rounded-md font-mono text-xs"
                    readonly
                  />
                  <Button
                    @click="copyTrackingCode"
                    variant="outline"
                    size="sm"
                    class="absolute top-2 right-2"
                  >
                    <Icon name="copy" class="mr-2 h-4 w-4" />
                    Copy
                  </Button>
                </div>
              </div>

              <div class="bg-muted p-4 rounded-lg">
                <h4 class="font-medium mb-2">Installation Instructions:</h4>
                <ol class="list-decimal list-inside space-y-1 text-sm text-muted-foreground">
                  <li>Copy the JavaScript code above</li>
                  <li>Paste it into the &lt;head&gt; section of your website</li>
                  <li>Make sure it appears on every page you want to track</li>
                  <li>Wait a few minutes for data to start appearing in your dashboard</li>
                </ol>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Danger Zone -->
      <Card>
        <CardHeader>
          <CardTitle class="text-red-600">Danger Zone</CardTitle>
          <CardDescription>
            Irreversible and destructive actions
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex items-center justify-between p-4 border border-red-200 rounded-lg bg-red-50">
            <div>
              <h4 class="font-medium text-red-900">Delete Site</h4>
              <p class="text-sm text-red-700">
                Permanently delete this site and all its analytics data. This action cannot be undone.
              </p>
            </div>
            <Button
              @click="confirmDelete"
              variant="destructive"
              size="sm"
            >
              <Icon name="trash-2" class="mr-2 h-4 w-4" />
              Delete Site
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'

interface Site {
  id: number
  name: string
  domain: string
  description: string | null
  tracking_id: string
  tracking_script: string
  is_active: boolean
}

interface Props {
  site: Site
}

const props = defineProps<Props>()

const form = useForm({
  name: props.site.name,
  domain: props.site.domain,
  description: props.site.description || '',
  is_active: props.site.is_active,
})

const processing = ref(false)

const updateSite = () => {
  processing.value = true
  form.put(route('sites.update', { site: props.site.id }), {
    onFinish: () => {
      processing.value = false
    }
  })
}

const copyTrackingCode = () => {
  const textarea = document.getElementById('tracking-code') as HTMLTextAreaElement
  textarea.select()
  document.execCommand('copy')
  
  // You could add a toast notification here
  alert('Tracking code copied to clipboard!')
}

const confirmDelete = () => {
  if (confirm('Are you sure you want to delete this site? This action cannot be undone.')) {
    router.delete(route('sites.destroy', { site: props.site.id }))
  }
}
</script> 