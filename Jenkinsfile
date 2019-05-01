elifePipeline {
    def commit
    stage 'Checkout', {
        checkout scm
        commit = elifeGitRevision()
    }

    stage 'Build image', {
        node('containers-jenkins-plugin') {
            checkout scm
            dockerComposeBuild commit
        }
    }

    stage 'Project tests', {
        lock('recommendations--ci') {
            builderDeployRevision 'recommendations--ci', commit
            builderProjectTests 'recommendations--ci', '/srv/recommendations', ['/srv/recommendations/build/phpunit.xml']
        }
    }

    elifeMainlineOnly {
        stage 'End2end tests', {

            elifeSpectrum(
                deploy: [
                    stackname: 'recommendations--end2end',
                    revision: commit,
                    folder: '/srv/recommendations'
                ],
                marker: 'recommendations'
            )
        }

        stage 'Deploy on continuumtest', {
            lock('recommendations--continuumtest') {
                builderDeployRevision 'recommendations--continuumtest', commit
                builderSmokeTests 'recommendations--continuumtest', '/srv/recommendations'
            }
        }

        stage 'Approval', {
            elifeGitMoveToBranch commit, 'approved'
        }
    }
}
