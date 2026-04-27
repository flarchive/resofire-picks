import app from 'flarum/admin/app';
import extenders from './extend';

export { default as extend } from './extend';

app.initializers.add('resofire/picks', () => {
  extenders.forEach((extender) => extender.extend(app));
});
