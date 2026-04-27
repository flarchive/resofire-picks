import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import Team from '../common/models/Team';
import PicksPage from './components/PicksPage';

export default [
  new Extend.Store().add('picks-teams', Team),

  new Extend.Routes().add('picks-admin', '/picks-admin', PicksPage),

  new Extend.Admin()
    .permission(
      () => ({
        icon: 'fas fa-football',
        label: app.translator.trans('resofire-picks.admin.permissions.manage'),
        permission: 'picks.manage',
      }),
      'moderate'
    )
    .permission(
      () => ({
        icon: 'fas fa-check-circle',
        label: app.translator.trans('resofire-picks.admin.permissions.make_picks'),
        permission: 'picks.makePicks',
      }),
      'start'
    )
    .permission(
      () => ({
        icon: 'fas fa-eye',
        label: app.translator.trans('resofire-picks.admin.permissions.view'),
        permission: 'picks.view',
        allowGuest: true,
      }),
      'view'
    ),
];
