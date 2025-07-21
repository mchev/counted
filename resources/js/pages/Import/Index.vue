<template>
  <AppLayout>

    <div class="flex items-center justify-between p-4">
        <Heading title="Import Data" />
        <div class="flex items-center space-x-2">
        <Button as="a" :href="route('import.history')" variant="outline">
            <Icon name="history" class="mr-2 h-4 w-4" />
            View History
        </Button>
        <Button as="a" :href="route('sites.index')" variant="outline">
            <Icon name="arrow-left" class="mr-2 h-4 w-4" />
            Back to Sites
        </Button>
        </div>
    </div>

    <div class="space-y-6">
      <!-- Import Umami -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center">
            <Icon name="database" class="mr-2 h-5 w-5" />
            Import from Umami
          </CardTitle>
          <CardDescription>
            Import your analytics data from a Umami SQL dump
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="importUmami" class="space-y-4">
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-start space-x-2">
                <Icon name="info" class="h-4 w-4 text-blue-600 mt-0.5" />
                <div>
                  <p class="text-sm font-medium text-blue-800">Automatic Site Detection</p>
                  <p class="text-xs text-blue-700 mt-1">
                    Sites will be automatically detected from the Umami dump. Existing sites with the same name or domain will be updated, new sites will be created.
                  </p>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="sqlFile">SQL Dump File</Label>
              
              <!-- Tabs pour choisir la méthode d'upload -->
              <div class="flex space-x-1 bg-muted p-1 rounded-lg">
                <button
                  type="button"
                  @click="uploadMethod = 'http'"
                  :class="[
                    'flex-1 px-3 py-2 text-sm rounded-md transition-colors',
                    uploadMethod === 'http' 
                      ? 'bg-background text-foreground shadow-sm' 
                      : 'text-muted-foreground hover:text-foreground'
                  ]"
                >
                  Upload HTTP
                </button>
                <button
                  type="button"
                  @click="uploadMethod = 'ftp'"
                  :class="[
                    'flex-1 px-3 py-2 text-sm rounded-md transition-colors',
                    uploadMethod === 'ftp' 
                      ? 'bg-background text-foreground shadow-sm' 
                      : 'text-muted-foreground hover:text-foreground'
                  ]"
                >
                  FTP/SFTP Files
                </button>
              </div>

              <!-- Upload HTTP -->
              <div v-if="uploadMethod === 'http'">
                <Input
                  id="sqlFile"
                  type="file"
                  accept=".sql,.gz,.sql.gz"
                  @change="handleFileSelect"
                  required
                />
                <p class="text-sm text-muted-foreground mt-1">
                  Upload your Umami SQL dump file (.sql or .gz compressed, max 2GB)
                </p>
                
                <!-- Option pour les très gros fichiers -->
                <div v-if="selectedFile && selectedFile.size > 500 * 1024 * 1024" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg mt-2">
                  <div class="flex items-start space-x-2">
                    <Icon name="alert-triangle" class="h-4 w-4 text-yellow-600 mt-0.5" />
                    <div>
                      <p class="text-sm font-medium text-yellow-800">Large File Detected</p>
                      <p class="text-xs text-yellow-700 mt-1">
                        This file is {{ formatFileSize(selectedFile.size) }}. For files larger than 500MB, 
                        consider using compression or FTP upload. The import will be processed in the background.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Sélection de fichiers FTP -->
              <div v-if="uploadMethod === 'ftp'">
                <div class="p-4 border rounded-lg bg-muted/50">
                  <div class="flex items-center space-x-2 mb-3">
                    <Icon name="folder" class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm font-medium">Available Files</span>
                  </div>
                  
                  <div v-if="ftpFiles.length === 0" class="text-center py-8">
                    <Icon name="folder-open" class="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                    <p class="text-sm text-muted-foreground">No files found in uploads directory</p>
                    <p class="text-xs text-muted-foreground mt-1">
                      Upload files via FTP to: <code class="bg-background px-1 rounded">storage/app/imports/</code>
                    </p>
                  </div>
                  
                  <div v-else class="space-y-2">
                    <div
                      v-for="file in ftpFiles"
                      :key="file.name"
                      @click="selectFtpFile(file)"
                      :class="[
                        'p-3 border rounded-lg cursor-pointer transition-colors',
                        selectedFtpFile?.name === file.name 
                          ? 'border-primary bg-primary/5' 
                          : 'border-border hover:border-primary/50'
                      ]"
                    >
                      <div class="flex items-center justify-between">
                        <div>
                          <p class="text-sm font-medium">{{ file.name }}</p>
                          <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(file.size) }} • {{ file.modified }}
                          </p>
                        </div>
                        <Icon 
                          v-if="selectedFtpFile?.name === file.name" 
                          name="check" 
                          class="h-4 w-4 text-primary" 
                        />
                      </div>
                    </div>
                  </div>
                </div>
                
                <p class="text-sm text-muted-foreground mt-2">
                  Upload files via FTP/SFTP to the server, then select them here.
                </p>
              </div>
            </div>

            <div v-if="selectedFile" class="p-3 bg-muted rounded-lg">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium">{{ selectedFile.name }}</p>
                  <p class="text-xs text-muted-foreground">
                    {{ formatFileSize(selectedFile.size) }}
                  </p>
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  @click="selectedFile = null"
                >
                  <Icon name="x" class="h-4 w-4" />
                </Button>
              </div>
            </div>

            <div class="flex items-center space-x-2">
              <Checkbox id="dryRun" v-model:checked="dryRun" />
              <Label for="dryRun">Dry run (preview import without saving)</Label>
            </div>

            <Button type="submit" :disabled="isImporting || (!selectedFile && !selectedFtpFile)">
              <Icon v-if="isImporting" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
              <Icon v-else name="upload" class="mr-2 h-4 w-4" />
              {{ isImporting ? 'Importing...' : 'Import Data' }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <!-- Active Imports Progress -->
      <Card v-if="activeImports.length > 0">
        <CardHeader>
          <CardTitle>Active Imports</CardTitle>
          <CardDescription>
            Currently processing imports
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div
              v-for="importItem in activeImports"
              :key="importItem.id"
              class="p-4 border rounded-lg"
            >
              <div class="flex items-center justify-between mb-2">
                <div>
                  <p class="text-sm font-medium">{{ importItem.site_name }}</p>
                  <p class="text-xs text-muted-foreground">
                    {{ importItem.file_name }} • {{ formatFileSize(importItem.details?.file_size || 0) }}
                  </p>
                </div>
                <span class="text-xs font-medium text-yellow-600">
                  {{ importItem.status }}
                </span>
              </div>
              
              <div v-if="importItem.details?.progress" class="space-y-2">
                <div class="flex justify-between text-xs text-muted-foreground">
                  <span>Progress</span>
                  <span>{{ importItem.details.progress.lines_processed?.toLocaleString() || 0 }} lines</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                    :style="{ width: getProgressPercentage(importItem) + '%' }"
                  ></div>
                </div>
                <div class="flex justify-between text-xs text-muted-foreground">
                  <span>{{ importItem.details.progress.page_views?.toLocaleString() || 0 }} page views</span>
                  <span>{{ importItem.details.progress.events?.toLocaleString() || 0 }} events</span>
                </div>
              </div>
              
              <div v-if="importItem.details?.estimated_duration" class="mt-2">
                <p class="text-xs text-muted-foreground">
                  Estimated time: {{ formatDuration(importItem.details.estimated_duration) }}
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Import History -->
      <Card v-if="importHistory.length > 0">
        <CardHeader>
          <CardTitle>Import History</CardTitle>
          <CardDescription>
            Recent import operations
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-3">
            <div
              v-for="importItem in importHistory"
              :key="importItem.id"
              class="flex items-center justify-between p-3 border rounded-lg"
            >
              <div>
                <p class="text-sm font-medium">{{ importItem.site_name }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ importItem.created_at }} • {{ importItem.status }}
                </p>
                <p v-if="importItem.summary" class="text-xs text-muted-foreground mt-1">
                  {{ importItem.summary }}
                </p>
                <p v-if="importItem.details?.file_size_formatted" class="text-xs text-muted-foreground">
                  File: {{ importItem.details.file_size_formatted }}
                </p>
              </div>
              <div class="flex items-center space-x-2">
                <span
                  :class="{
                    'text-green-600': importItem.status === 'completed',
                    'text-yellow-600': importItem.status === 'processing',
                    'text-red-600': importItem.status === 'failed'
                  }"
                  class="text-xs font-medium"
                >
                  {{ importItem.status }}
                </span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Import Instructions -->
      <Card>
        <CardHeader>
          <CardTitle>How to Export from Umami</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="prose prose-sm max-w-none">
            <h4>1. Export your Umami database</h4>
            <p>From your Umami instance, export your database:</p>
            <pre class="bg-muted p-3 rounded text-sm"># Normal export
mysqldump -u username -p umami > umami_dump.sql

# Compressed export (recommended for large databases)
mysqldump -u username -p umami | gzip > umami_dump.sql.gz</pre>
            
            <h4>2. Upload the SQL file</h4>
            <p>Select your site and upload the exported SQL file (.sql or .gz compressed).</p>
            
            <h4>3. Review and import</h4>
            <p>Use the dry run option to preview the import before proceeding.</p>
            
            <h4>File Compression</h4>
            <ul>
              <li>Compressed files (.gz) are automatically detected and decompressed</li>
              <li>Compression can reduce upload time by 70-90%</li>
              <li>Recommended for databases larger than 100MB</li>
              <li>Both .sql and .sql.gz files are supported</li>
            </ul>
            
            <h4>Supported Data</h4>
            <ul>
              <li>Page views and sessions</li>
              <li>Custom events</li>
              <li>Website configurations</li>
              <li>User data (anonymized)</li>
            </ul>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'

interface Site {
  id: number
  name: string
  domain: string
}

interface ImportHistory {
  id: number
  site_name: string
  status: 'processing' | 'completed' | 'failed'
  created_at: string
  summary?: string
  details?: any
  file_name?: string
}

interface Props {
  sites: Site[]
  importHistory: ImportHistory[]
}

const props = defineProps<Props>()

const selectedSite = ref<number | null>(null)
const selectedFile = ref<File | null>(null)
const dryRun = ref(false)
const isImporting = ref(false)
const uploadMethod = ref<'http' | 'ftp'>('http') // Default to HTTP upload
const ftpFiles = ref<any[]>([]) // Placeholder for FTP files
const selectedFtpFile = ref<any | null>(null)

// Computed pour filtrer les imports actifs
const activeImports = computed(() => {
  return props.importHistory.filter(item => item.status === 'processing')
})

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0]
  }
}

const selectFtpFile = (file: any) => {
  selectedFtpFile.value = file
  selectedFile.value = null // Clear HTTP file selection
}

const loadFtpFiles = async () => {
  try {
    const response = await fetch(route('import.ftp-files'))
    if (response.ok) {
      ftpFiles.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load FTP files:', error)
  }
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatDuration = (seconds: number): string => {
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
  return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
}

const getProgressPercentage = (importItem: ImportHistory): number => {
  if (!importItem.details?.progress?.lines_processed || !importItem.details?.estimated_records) {
    return 0
  }
  return Math.min(100, (importItem.details.progress.lines_processed / importItem.details.estimated_records) * 100)
}

const importUmami = async () => {
  if (!selectedFile.value && !selectedFtpFile.value) return

  isImporting.value = true

  const formData = new FormData()
  formData.append('dry_run', dryRun.value.toString())
  
  if (uploadMethod.value === 'http' && selectedFile.value) {
    formData.append('sql_file', selectedFile.value)
  } else if (uploadMethod.value === 'ftp' && selectedFtpFile.value) {
    formData.append('ftp_file', selectedFtpFile.value.name)
  }

  try {
    await router.post(route('import.umami'), formData, {
      onSuccess: (page) => {
        // Reset form
        selectedFile.value = null
        selectedFtpFile.value = null
        dryRun.value = false
        
        // Reset file input
        const fileInput = document.getElementById('sqlFile') as HTMLInputElement
        if (fileInput) fileInput.value = ''
        
        // Si c'est un job en arrière-plan, rafraîchir la page pour voir le statut
        if (page.props.flash?.job_queued) {
          router.reload()
        }
      },
      onError: (errors) => {
        console.error('Import failed:', errors)
      },
      onFinish: () => {
        isImporting.value = false
      }
    })
  } catch (error) {
    console.error('Import error:', error)
    isImporting.value = false
  }
}

// Rafraîchir périodiquement pour voir les mises à jour des imports actifs
onMounted(() => {
  // Charger les fichiers FTP disponibles
  loadFtpFiles()
  
  if (activeImports.value.length > 0) {
    const interval = setInterval(() => {
      router.reload({ only: ['importHistory'] })
    }, 5000) // Rafraîchir toutes les 5 secondes
    
    // Nettoyer l'intervalle quand le composant est détruit
    return () => clearInterval(interval)
  }
})
</script> 