function requireAll(r) {
  r.keys().forEach(r);
}

requireAll(require.context('../../../assets/images/', true, /\.svg$/));
