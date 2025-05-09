<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="editVersion tagManagerManageEdit">
    <ContentBlock
      feature="Tag Manager"
      :content-title="editTitle"
    >
      <p v-show="isLoading">
        <span class="loadingPiwik">
          <img src="plugins/Morpheus/images/loading-blue.gif"/>
          {{ translate('General_LoadingData') }}
        </span>
      </p>
      <p v-show="isUpdating">
        <span class="loadingPiwik">
          <img src="plugins/Morpheus/images/loading-blue.gif"/>
          {{ translate('TagManager_UpdatingData') }}
        </span>
      </p>
      <form @submit="edit ? updateVersion() : createVersion()">
        <div>
          <div v-if="hasPublishCapability() ||
          (hasWriteCapability() && hasPublishToLiveCapability())">
            <div>
              <Field
                uicontrol="text"
                name="name"
                :inline-help="versionNameHelpText"
                :inline-help-bind="{ lastVersion }"
                :model-value="version.name"
                @update:model-value="version.name = $event; setValueHasChanged()"
                :maxlength="50"
                :title="translate('TagManager_VersionName')"
                :placeholder="translate('TagManager_VersionNamePlaceholder')"
              />
            </div>
            <div>
              <Field
                uicontrol="textarea"
                name="description"
                :model-value="version.description"
                @update:model-value="version.description = $event; setValueHasChanged()"
                :title="translate('TagManager_VersionDescriptionOptional')"
                :inline-help="translate('TagManager_VersionDescriptionHelp')"
                :placeholder="translate('TagManager_VersionDescriptionPlaceholder')"
              />
            </div>
            <SaveButton
              class="createButton no-publish"
              @confirm="edit ? updateVersion() : createVersion()"
              :disabled="isUpdating || !isDirty"
              :saving="isUpdating"
              :value="edit
              ? translate('CoreUpdater_UpdateTitle') :
              translate('TagManager_CreateVersionWithoutPublishing')"
            >
            </SaveButton>
            <div v-if="create && environments.length">
              <Field
                uicontrol="select"
                name="environment"
                :inline-help="selectTagManagerEnvironmentHelp"
                :inline-help-bind="{ canPublishToLive }"
                :model-value="version.environments?.[0]"
                @update:model-value="version.environments[0] = $event; setValueHasChanged()"
                :options="environments"
                :introduction="translate('TagManager_OrCreateAndPublishVersion')"
                :title="translate('TagManager_Environment')"
              />
            </div>
            <SaveButton
              class="publishButton"
              v-if="create && environments.length"
              @confirm="createVersionAndPublish()"
              :disabled="isUpdating || !isDirty"
              :saving="isUpdating"
              :value="translate('TagManager_CreateVersionAndPublishRelease')"
            >
            </SaveButton>
          </div>
          <div v-else>
            <div class="alert alert-warning"
                 v-html="$sanitize(showNoAccessErrorMessage)"
            >
            </div>
          </div>
          <div
            class="versionChanges"
            v-if="lastVersion"
          >
            <h3>{{ translate('TagManager_ChangesSinceLastVersion') }}:</h3>
            <table v-content-table>
              <thead>
                <tr>
                  <th>{{ translate('SitesManager_Type') }}</th>
                  <th>{{ translate('General_Name') }}</th>
                  <th>{{ translate('TagManager_Change') }}</th>
                  <th>{{ translate('TagManager_LastUpdated') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoadingVersionChanges">
                  <td colspan="4">
                    <ActivityIndicator
                      :loading-message="translate('TagManager_DetectingChanges')"
                      :loading="true"
                    />
                  </td>
                </tr>
                <tr v-if="!versionChanges.length && !isLoadingVersionChanges">
                  <td colspan="4">{{ translate('UserCountryMap_None') }}</td>
                </tr>
                <tr v-for="(versionChange, index) in versionChanges" :key="index">
                  <td>{{ translate(versionChange.entityType) }}</td>
                  <td>{{ versionChange.name }}</td>
                  <td>{{ translate(versionChange.type) }}</td>
                  <td class="lastUpdated"><span>{{ versionChange.lastChanged }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            class="entityCancel"
            v-show="!isEmbedded"
          >
            <a @click="cancel()">{{ translate('General_Cancel') }}</a>
          </div>
        </div>
      </form>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import { defineComponent, markRaw } from 'vue';
import {
  Matomo,
  translate,
  ContentBlock,
  ContentTable,
  ActivityIndicator,
  NotificationsStore,
  NotificationType,
  clone,
  MatomoUrl, externalLink,
} from 'CoreHome';
import { Field, SaveButton } from 'CorePluginsAdmin';
import AvailableEnvironmentsStore from '../AvailableEnvironments.store';
import VariablesStore from '../Variable/Variables.store';
import AvailableComparisonsStore from '../AvailableComparisons.store';
import diffDraftVersion, { SingleDiff } from './diffDraftVersion';
import { Version } from '../types';
import VersionsStore from './Versions.store';
import VersionNameHelpText from './VersionNameHelpText.vue';
import SelectTagManagerEnvironmentHelpText from './SelectTagManagerEnvironmentHelpText.vue';

interface VersionEditState {
  isDirty: boolean;
  lastVersion: string | null;
  versionChanges: SingleDiff[];
  isLoadingVersionChanges: boolean;
  isUpdatingVersion: boolean;
  version: Version;
}

const notificationId = 'versiontagmanagement';

export default defineComponent({
  props: {
    idContainerVersion: {
      type: Number,
      required: true,
    },
    idContainer: {
      type: String,
      required: true,
    },
    isEmbedded: {
      type: Boolean,
      default: false,
    },
  },
  components: {
    ContentBlock,
    Field,
    SaveButton,
    ActivityIndicator,
  },
  directives: {
    ContentTable,
  },
  data(): VersionEditState {
    return {
      isDirty: false,
      lastVersion: null,
      versionChanges: [],
      isLoadingVersionChanges: false,
      isUpdatingVersion: false,
      version: {} as unknown as Version,
    };
  },
  emits: ['changeVersion'],
  created() {
    AvailableComparisonsStore.init();
    AvailableEnvironmentsStore.init();
    this.initIdContainerVersion();
  },
  watch: {
    idContainerVersion(newValue) {
      if (newValue === null) {
        return;
      }

      this.initIdContainerVersion();
    },
  },
  methods: {
    removeAnyVersionNotification() {
      NotificationsStore.remove(notificationId);
      NotificationsStore.remove('ajaxHelper');
    },
    showNotification(message: string, context: NotificationType['context'],
      type: null|NotificationType['type'] = null) {
      const notificationInstanceId = NotificationsStore.show({
        message,
        context,
        id: notificationId,
        type: type !== null ? type : 'toast',
      });
      setTimeout(() => {
        NotificationsStore.scrollToNotification(notificationInstanceId);
      }, 200);
    },
    showErrorFieldNotProvidedNotification(title: string) {
      const message = translate('TagManager_ErrorXNotProvided', [title]);
      this.showNotification(message, 'error');
    },
    initIdContainerVersion() {
      this.version = {} as unknown as Version;

      this.lastVersion = null;
      this.versionChanges = [];
      this.isLoadingVersionChanges = true;

      VersionsStore.fetchVersions(this.idContainer).then(() => {
        this.isLoadingVersionChanges = false;
        this.lastVersion = null;

        const versions = [...VersionsStore.versions.value];
        if (!versions?.length) {
          return;
        }

        versions.sort((a, b) => (a.revision < b.revision ? 1 : 0));

        let lastContainerVersion = null;

        if (this.create && versions[0]?.name) {
          this.lastVersion = Matomo.helper.htmlEntities(versions[0].name);
          lastContainerVersion = versions[0].idcontainerversion;
        } else if (this.edit) {
          versions.forEach((v, i) => {
            // we stop before the last one because it cannot have an entry
            if (i >= versions.length - 1) {
              return;
            }

            if (v.idcontainerversion === this.idContainerVersion && versions[i + 1]) {
              this.lastVersion = Matomo.helper.htmlEntities(versions[i + 1].name);
              lastContainerVersion = versions[i + 1].idcontainerversion;
            }
          });
        }

        if (this.lastVersion) {
          this.isLoadingVersionChanges = true;
          diffDraftVersion(
            this.idContainer,
            this.idContainerVersion,
            lastContainerVersion!,
          ).then((diff) => {
            diff.sort((a, b) => (
              new Date(b.lastChanged).valueOf() - new Date(a.lastChanged).valueOf()));
            this.versionChanges = diff;
            this.isLoadingVersionChanges = false;
          });

          if (this.create && !this.version.name && /^\d+$/.test(this.lastVersion)) {
            this.version.name = `${parseInt(this.lastVersion, 10) + 1}`;
            this.isDirty = true;
          }
        }
      });

      Matomo.helper.lazyScrollToContent();

      if (this.edit && this.idContainerVersion) {
        VersionsStore.findVersion(this.idContainer, this.idContainerVersion).then((version) => {
          if (!version) {
            return;
          }

          this.version = clone(version) as unknown as Version;
          this.isDirty = false;
        });

        return;
      }

      if (this.create) {
        this.version = {
          idSite: Matomo.idSite,
          idcontainer: this.idContainer,
          name: '',
          description: '',
        } as unknown as Version;

        if (this.canPublishToLive) {
          this.version.environments = ['live'];
        } else {
          // If the user can't publish to live, select the next available option.
          const notLive = this.environments.find((obj) => obj.key !== 'live');
          this.version.environments = notLive ? [notLive.key] : [];
        }

        this.isDirty = false;
      }
    },
    cancel() {
      const newParams = { ...MatomoUrl.hashParsed.value };
      delete newParams.idContainerVersion;
      MatomoUrl.updateHash(newParams);
    },
    createVersion() {
      this.removeAnyVersionNotification();

      if (!this.checkRequiredFieldsAreSet()) {
        return;
      }

      this.isUpdatingVersion = true;
      VersionsStore.createOrUpdateVersion(
        this.version,
        'TagManager.createContainerVersion',
        this.idContainer,
      ).then((response) => {
        if (!response) {
          return;
        }

        this.isDirty = false;

        const idContainerVersion = response.value;

        if (this.isEmbedded) {
          this.version.idcontainerversion = idContainerVersion;
          this.$emit('changeVersion', {
            version: this.version,
          });
          return;
        }

        VersionsStore.reload(this.idContainer).then(() => {
          MatomoUrl.updateHash({
            ...MatomoUrl.hashParsed.value,
            idContainerVersion,
          });

          setTimeout(() => {
            const createdX = translate('TagManager_CreatedX', translate('TagManager_Version'));
            if (this.hasPublishCapability()) {
              const wantToRedeploy = translate(
                'TagManager_WantToDeployThisChangeCreateVersion',
                '<a class="createNewVersionLink">',
                '</a>',
              );
              this.showNotification(`${createdX} ${wantToRedeploy}`, 'success', 'transient');
              return;
            }

            this.showNotification(createdX, 'success');
          }, 200);
        });
      }).finally(() => {
        this.isUpdatingVersion = false;
      });
    },
    createVersionAndPublish() {
      this.removeAnyVersionNotification();

      if (!this.checkRequiredFieldsAreSet()) {
        return;
      }

      this.isUpdatingVersion = true;
      VersionsStore.createOrUpdateVersion(
        this.version,
        'TagManager.createContainerVersion',
        this.idContainer,
      ).then((response) => {
        if (!response) {
          return null;
        }

        const idContainerVersion = response.value;

        this.version.idcontainerversion = idContainerVersion;
        return VersionsStore.publishVersion(
          this.idContainer,
          idContainerVersion,
          this.version.environments[0],
        ).then(() => {
          this.isDirty = false;

          if (this.isEmbedded) {
            this.$emit('changeVersion', {
              version: this.version,
            });
            return;
          }

          VersionsStore.reload(this.idContainer).then(() => {
            MatomoUrl.updateHash({
              ...MatomoUrl.hashParsed.value,
              idContainerVersion,
            });

            setTimeout(() => {
              this.showNotification(translate('TagManager_VersionPublishSuccess'), 'success');
            }, 200);
          });
        });
      }).finally(() => {
        this.isUpdatingVersion = false;
      });
    },
    setValueHasChanged() {
      this.isDirty = true;
    },
    updateVersion() {
      this.removeAnyVersionNotification();

      if (!this.checkRequiredFieldsAreSet()) {
        return;
      }

      this.isUpdatingVersion = true;
      VersionsStore.createOrUpdateVersion(
        this.version,
        'TagManager.updateContainerVersion',
        this.idContainer,
      ).then((response) => {
        if (!response) {
          return;
        }

        if (this.isEmbedded) {
          this.$emit('changeVersion', {
            version: this.version,
          });
          return;
        }

        this.isDirty = false;
        this.version = {} as unknown as Version;

        VersionsStore.reload(this.idContainer).then(() => {
          this.initIdContainerVersion();
        });

        this.showNotification(translate('TagManager_UpdatedX', translate('TagManager_Version')), 'success');
      }).finally(() => {
        this.isUpdatingVersion = false;
      });
    },
    checkRequiredFieldsAreSet() {
      if (!this.version.name) {
        const title = translate('General_Name');
        this.showErrorFieldNotProvidedNotification(title);
        return false;
      }

      return true;
    },
    hasPublishCapability() {
      return this.hasWriteCapability() && Matomo.hasUserCapability('tagmanager_use_custom_templates');
    },
    hasWriteCapability() {
      return Matomo.hasUserCapability('tagmanager_write');
    },
    hasPublishToLiveCapability() {
      return Matomo.hasUserCapability('tagmanager_publish_live_container');
    },
  },
  computed: {
    create() {
      return this.idContainerVersion === 0;
    },
    edit() {
      return !this.create;
    },
    isLoading() {
      return VariablesStore.isLoading.value || AvailableComparisonsStore.isLoading.value;
    },
    isUpdating() {
      return VariablesStore.isUpdating.value || this.isUpdatingVersion;
    },
    environments() {
      const environments = AvailableEnvironmentsStore.environmentsWithPublishOptions.value;
      if (!this.canPublishToLive) {
        // If the user can't publish to live, disable that option.
        const liveIndex = environments.findIndex((obj) => obj.key === 'live');
        if (liveIndex > -1) {
          environments[liveIndex].disabled = true;
        }
      }
      return environments;
    },
    canPublishToLive() {
      return Matomo.hasUserCapability('tagmanager_publish_live_container');
    },
    versionNameHelpText() {
      return markRaw(VersionNameHelpText);
    },
    selectTagManagerEnvironmentHelp() {
      return markRaw(SelectTagManagerEnvironmentHelpText);
    },
    editTitle() {
      return this.create
        ? translate('TagManager_CreateNewVersion')
        : translate('TagManager_EditVersion');
    },
    showNoAccessErrorMessage() {
      return translate(
        'TagManager_VersionEditWithNoAccessMessage',
        externalLink('https://matomo.org/faq/tag-manager/faq_26547/'),
        '</a>',
      );
    },
  },
});
</script>
