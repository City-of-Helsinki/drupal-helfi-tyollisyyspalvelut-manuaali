jest.mock('path', () => ({
  resolve: (...paths) => `${paths[1]}${paths[2]}`,
}));
jest.mock('twig-drupal-filters', () => jest.fn());
jest.mock('bem-twig-extension', () => jest.fn());
jest.mock('add-attributes-twig-extension', () => jest.fn());

const Twig = require('twig');
const twigDrupal = require('twig-drupal-filters');
const twigBEM = require('bem-twig-extension');
const twigAddAttributes = require('add-attributes-twig-extension');

const { namespaces, setupTwig } = require('./setupTwig');

describe('setupTwig', () => {
  it('sets up a twig object with drupal, bem, and attribute decorations', () => {
    expect.assertions(3);
    setupTwig(Twig);
    expect(twigDrupal).toHaveBeenCalledWith(Twig);
    expect(twigBEM).toHaveBeenCalledWith(Twig);
    expect(twigAddAttributes).toHaveBeenCalledWith(Twig);
  });

  it('exports emulsifys namespaces', () => {
    expect(namespaces).toEqual({
      base: '../components/00-base',
      atoms: '../components/01-atoms',
      molecules: '../components/02-molecules',
      organisms: '../components/03-organisms',
      templates: '../components/04-templates',
      pages: '../components/05-pages',
    });
  });
});
