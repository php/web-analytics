<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="tagManagerManageList tagManagerTriggerList">
    <ContentBlock
      feature="Tag Manager"
      :content-title="translate('TagManager_ManageX', translate('TagManager_Triggers'))"
      :help-text="triggersHelpText"
    >
      <p>{{ translate('TagManager_TriggerUsageBenefits') }}</p>
      <div class="triggerSearchFilter">
        <Field
          uicontrol="text"
          name="triggerSearch"
          :title="translate('General_Search')"
          v-show="triggers.length > 0"
          v-model="triggerSearch"
        >
        </Field>
      </div>
      <table v-content-table>
        <thead>
          <tr>
            <th class="name" :title="nameTranslatedText">{{ translate('General_Name') }}</th>
            <th class="description" :title="descriptionTranslatedText">
              {{ translate('General_Description') }}</th>
            <th class="type" :title="typeTranslatedText">{{ translate('TagManager_Type') }}</th>
            <th class="conditions"
              :title="filterTranslatedText">{{ translate('TagManager_Filter') }}</th>
            <th class="lastUpdated"
              :title="lastUpdatedTranslatedText">{{ translate('TagManager_LastUpdated') }}</th>
            <th
              class="action"
              :title="actionTranslatedText"
              v-show="hasWriteAccess"
            >
              {{ translate('General_Actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-show="isLoading || isUpdating">
            <td colspan="7">
              <span class="loadingPiwik">
                <img src="plugins/Morpheus/images/loading-blue.gif" />
                {{ translate('General_LoadingData') }}
              </span>
            </td>
          </tr>
          <tr v-show="!isLoading && triggers.length === 0">
            <td colspan="7">
              {{ translate('TagManager_NoTriggersFound') }}
              <a
                class="createContainerTriggerNow"
                v-show="hasWriteAccess"
                @click="createTrigger()"
              >{{ translate('TagManager_CreateNewTriggerNow') }}</a>
            </td>
          </tr>
          <tr
            :id="`trigger${trigger.idtrigger}`"
            class="triggers"
            v-for="trigger in sortedTriggers"
            :key="trigger.idtrigger"
          >
            <td class="name" :title="trigger.name">{{ truncateText(trigger.name, 50) }}</td>
            <td
              class="description"
              :title="trigger.description"
            >
              {{ truncateText(trigger.description, 75) }}
            </td>
            <td
              class="type"
              :title="trigger.typeMetadata.description"
            >{{ trigger.typeMetadata.name }}</td>
            <td class="conditions">
              <span
                class="icon-ok"
                v-show="trigger.conditions?.length"
              />
            </td>
            <td
              class="lastUpdated"
              :title="translate('TagManager_CreatedOnX', trigger.created_date_pretty)"
            >
              <span>{{ trigger.updated_date_pretty }}</span>
            </td>
            <td
              :class="getActionClasses"
              v-show="hasWriteAccess"
            >
              <a
                class="table-action icon-edit"
                @click="editTrigger(trigger.idtrigger, trigger.type)"
                :title="translate('TagManager_EditTrigger')"
              />
              <a
                class="table-action icon-delete"
                @click="deleteTrigger(trigger)"
                :title="translate('TagManager_DeleteX', translate('TagManager_Trigger'))"
              />
              <a
                class="table-action icon-content-copy"
                v-show="hasPublishCapability()"
                @click="openCopyDialog(trigger)"
                :title="translate(
                  'TagManager_CopyX',
                  translate('TagManager_Trigger'),
                )"
              />
            </td>
          </tr>
        </tbody>
      </table>
      <div
        class="tableActionBar"
        v-show="hasWriteAccess"
      >
        <a
          class="createNewTrigger"
          value
          @click="createTrigger()"
        >
          <span class="icon-add">&nbsp;</span>{{ translate('TagManager_CreateNewTrigger') }}
        </a>
      </div>
    </ContentBlock>
    <div
      class="ui-confirm"
      id="confirmDeleteTrigger"
      ref="confirmDeleteTrigger"
    >
      <h2>{{ translate('TagManager_DeleteTriggerConfirm') }} </h2>
      <input
        role="yes"
        type="button"
        :value="translate('General_Yes')"
      />
      <input
        role="no"
        type="button"
        :value="translate('General_No')"
      />
    </div>
    <div
      class="ui-confirm"
      id="confirmDeleteTriggerNotPossible"
      ref="confirmDeleteTriggerNotPossible"
    >
      <h2>{{ translate('TagManager_TriggerCannotBeDeleted') }}</h2>
      <p>{{ translate('TagManager_TriggerBeingUsedBy') }}</p>
      <ul class="collection">
        <li
          class="collection-item"
          v-for="reference in triggerReferences"
          :key="reference.referenceId"
        >
          {{ reference.referenceTypeName }}: {{ reference.referenceName }}
        </li>
      </ul>
      <p>{{ translate('TagManager_TriggerBeingUsedNeedsRemove') }}</p>
      <input
        role="no"
        type="button"
        :value="translate('General_Cancel')"
      />
    </div>
  </div>
</template>

<script lang="ts">
import { DeepReadonly, defineComponent } from 'vue';
import {
  AjaxHelper,
  Matomo,
  ContentBlock,
  ContentTable,
  MatomoUrl,
  NotificationsStore,
} from 'CoreHome';
import { Field } from 'CorePluginsAdmin';
import TriggersStore from './Triggers.store';
import { TriggerReference, Trigger, TriggerType } from '../types';

interface TriggerListState {
  hasWriteAccess: boolean;
  triggerReferences: TriggerReference[];
  triggerSearch: string;
}

const { tagManagerHelper } = window;

export default defineComponent({
  props: {
    idContainer: {
      type: String,
      required: true,
    },
    idContainerVersion: {
      type: Number,
      required: true,
    },
    triggersHelpText: String,
  },
  components: {
    Field,
    ContentBlock,
  },
  directives: {
    ContentTable,
  },
  data(): TriggerListState {
    return {
      hasWriteAccess: Matomo.hasUserCapability('tagmanager_write'),
      triggerReferences: [],
      triggerSearch: '',
    };
  },
  created() {
    TriggersStore.fetchTriggers(this.idContainer, this.idContainerVersion);
  },
  methods: {
    createTrigger() {
      this.editTrigger(0);
    },
    editTrigger(idTrigger: number) {
      MatomoUrl.updateHash({
        ...MatomoUrl.hashParsed.value,
        idTrigger,
      });
    },
    deleteTrigger(trigger: DeepReadonly<Trigger>) {
      AjaxHelper.fetch({
        method: 'TagManager.getContainerTriggerReferences',
        idContainer: this.idContainer,
        idContainerVersion: this.idContainerVersion,
        idTrigger: trigger.idtrigger,
      }).then((references) => {
        if (!references || !references.length) {
          this.triggerReferences = [];

          const doDelete = () => {
            TriggersStore.deleteTrigger(
              this.idContainer,
              this.idContainerVersion,
              trigger.idtrigger!,
            ).then(() => {
              TriggersStore.reload(this.idContainer, this.idContainerVersion);
              NotificationsStore.remove('CopyDialogResultNotification');
            });
          };

          Matomo.helper.modalConfirm(this.$refs.confirmDeleteTrigger as HTMLElement, {
            yes: doDelete,
          });
        } else {
          this.triggerReferences = references;
          Matomo.helper.modalConfirm(this.$refs.confirmDeleteTriggerNotPossible as HTMLElement, {});
        }
      });
    },
    truncateText(text: string, length: number) {
      return tagManagerHelper.truncateText(text, length);
    },
    hasPublishCapability() {
      return Matomo.hasUserCapability('tagmanager_write') && Matomo.hasUserCapability('tagmanager_use_custom_templates');
    },
    openCopyDialog(trigger: Trigger) {
      const url = MatomoUrl.stringify({
        module: 'TagManager',
        action: 'copyTriggerDialog',
        idSite: trigger.idsite,
        idContainer: this.idContainer,
        idTrigger: trigger.idtrigger,
        idContainerVersion: this.idContainerVersion,
      });
      window.Piwik_Popover.createPopupAndLoadUrl(url, '', 'mtmCopyTrigger');
    },
  },
  computed: {
    isLoading() {
      return TriggersStore.isLoading.value;
    },
    isUpdating() {
      return TriggersStore.isUpdating.value;
    },
    triggers() {
      return TriggersStore.triggers.value;
    },
    sortedTriggers() {
      const searchFilter = this.triggerSearch.toLowerCase();
      // look through string properties of triggers for values that have searchFilter in them
      // (mimics angularjs filter() filter)
      const result = [...this.triggers].filter((h) => Object.keys(h).some((propName) => {
        const entity = h as unknown as Record<string, unknown>;
        let propValue = '';
        if (typeof entity[propName] === 'string') {
          propValue = (entity[propName] as string);
        } else if (propName === 'typeMetadata') {
          const propTypeMeta = (entity.typeMetadata as TriggerType);
          propValue = (propTypeMeta.name as string);
        } else if (propName === 'parameters' && entity.type === 'CustomEvent') {
          const propTypeParameters = (entity.parameters as Record<string, unknown>);
          propValue = (propTypeParameters.eventName as string);
        }
        return propValue.toLowerCase().indexOf(searchFilter) !== -1;
      }));
      result.sort((lhs, rhs) => {
        if (lhs.name < rhs.name) {
          return -1;
        }
        return lhs.name > rhs.name ? 1 : 0;
      });
      return result;
    },
    nameTranslatedText(): string {
      return this.translate('TagManager_TriggersNameDescription');
    },
    descriptionTranslatedText(): string {
      return this.translate('TagManager_TriggersDescriptionDescription');
    },
    typeTranslatedText(): string {
      return this.translate('TagManager_TriggersTypeDescription');
    },
    filterTranslatedText(): string {
      return this.translate('TagManager_TriggersFilterDescription');
    },
    lastUpdatedTranslatedText(): string {
      return this.translate('TagManager_TriggersLastUpdatedDescription');
    },
    actionTranslatedText(): string {
      return this.translate('TagManager_TriggersActionDescription');
    },
    getActionClasses(): string {
      const copyClass = this.hasPublishCapability() ? ' hasCopyAction' : '';
      return `action${copyClass}`;
    },
  },
});
</script>
