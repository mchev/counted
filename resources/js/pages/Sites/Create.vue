<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <Heading title="Add New Site" />
        <Button as="a" :href="route('sites.index')" variant="outline">
          <Icon name="arrow-left" class="mr-2 h-4 w-4" />
          Back to Sites
        </Button>
      </div>
    </template>

    <div class="max-w-2xl mx-auto">
      <Card>
        <CardHeader>
          <CardTitle>Site Information</CardTitle>
          <CardDescription>
            Add a new website to start tracking analytics
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <Label for="name">Site Name</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="My Awesome Website"
                required
              />
              <InputError v-if="form.errors.name" :message="form.errors.name" />
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
              <p class="text-sm text-muted-foreground mt-1">
                Enter your domain without http:// or https://
              </p>
              <InputError v-if="form.errors.domain" :message="form.errors.domain" />
            </div>

            <div>
              <Label for="description">Description (Optional)</Label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 border border-input bg-background text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 rounded-md"
                placeholder="A brief description of your website..."
              />
              <InputError v-if="form.errors.description" :message="form.errors.description" />
            </div>

            <div class="flex items-center justify-end space-x-2 pt-4">
              <Button
                type="button"
                variant="outline"
                @click="$inertia.visit(route('sites.index'))"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                Create Site
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'
import InputError from '@/components/InputError.vue'

const form = useForm({
  name: '',
  domain: '',
  description: '',
})

const submit = () => {
  form.post(route('sites.store'))
}
</script> 